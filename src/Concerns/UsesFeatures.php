<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\FeatureContract;
use OffloadProject\Mandate\Models\Feature;

/**
 * Provides feature flag functionality for models.
 *
 * @mixin Model
 */
trait UsesFeatures
{
    /**
     * Get the features relationship.
     */
    public function features(): MorphToMany
    {
        /** @var class-string<FeatureContract&Model> $featureClass */
        $featureClass = config('mandate.models.feature', Feature::class);
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');
        $contextEnabled = config('mandate.context.subject_features', false);

        $relation = $this->morphToMany(
            $featureClass,
            $subjectMorphKey,
            config('mandate.tables.subject_features', 'mandate_subject_features'),
            "{$subjectMorphKey}_id",
            config('mandate.columns.pivot_feature_key', 'feature_id')
        );

        if ($contextEnabled) {
            $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
            $relation->withPivot(['scope', "{$contextMorphName}_type", "{$contextMorphName}_id"]);
        }

        return $relation->withTimestamps();
    }

    /**
     * Check if the model has access to a specific feature.
     * Checks both that the feature is active globally AND active for this model via Pennant.
     */
    public function hasAccess(
        string|FeatureContract $feature,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        $featureName = $this->resolveFeatureName($feature);

        // If Pennant is available, use it for the authoritative check
        if ($this->isPennantAvailable()) {
            return $this->checkPennantAccess($featureName);
        }

        // Fall back to checking stored feature assignments
        return $this->getFeaturesQuery($scope, $contextModel)
            ->where('name', $featureName)
            ->exists();
    }

    /**
     * Alias for hasAccess - check if feature is enabled for this model.
     */
    public function enabled(string|FeatureContract $feature): bool
    {
        return $this->hasAccess($feature);
    }

    /**
     * Check if a feature is disabled for this model.
     */
    public function disabled(string|FeatureContract $feature): bool
    {
        return ! $this->hasAccess($feature);
    }

    /**
     * Check if the model has access to any of the given features.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function hasAnyAccess(
        iterable $features,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        foreach ($features as $feature) {
            if ($this->hasAccess($feature, $scope, $contextModel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alias for hasAnyAccess.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function anyEnabled(iterable $features): bool
    {
        return $this->hasAnyAccess($features);
    }

    /**
     * Check if the model has access to all of the given features.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function hasAllAccess(
        iterable $features,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        foreach ($features as $feature) {
            if (! $this->hasAccess($feature, $scope, $contextModel)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for hasAllAccess.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function allEnabled(iterable $features): bool
    {
        return $this->hasAllAccess($features);
    }

    /**
     * Check if all features are disabled.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function allDisabled(iterable $features): bool
    {
        foreach ($features as $feature) {
            if ($this->hasAccess($feature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any features are disabled.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function anyDisabled(iterable $features): bool
    {
        foreach ($features as $feature) {
            if (! $this->hasAccess($feature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enable a feature for this model.
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function enable(string|iterable|FeatureContract $features): static
    {
        $featureNames = $this->resolveFeatureNames($features);

        // Use Pennant if available
        if ($this->isPennantAvailable()) {
            foreach ($featureNames as $name) {
                $this->activatePennantFeature($name);
            }
        }

        return $this;
    }

    /**
     * Disable a feature for this model.
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function disable(string|iterable|FeatureContract $features): static
    {
        $featureNames = $this->resolveFeatureNames($features);

        // Use Pennant if available
        if ($this->isPennantAvailable()) {
            foreach ($featureNames as $name) {
                $this->deactivatePennantFeature($name);
            }
        }

        return $this;
    }

    /**
     * Forget a feature state for this model (resets to default resolution).
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function forget(string|iterable|FeatureContract $features): static
    {
        $featureNames = $this->resolveFeatureNames($features);

        // Use Pennant if available
        if ($this->isPennantAvailable()) {
            foreach ($featureNames as $name) {
                $this->forgetPennantFeature($name);
            }
        }

        return $this;
    }

    /**
     * Get all feature names for the model.
     *
     * @return array<string>
     */
    public function featureNames(): array
    {
        return $this->allFeatures()->pluck('name')->all();
    }

    /**
     * Get all features for the model.
     *
     * @return Collection<int, FeatureContract>
     */
    public function allFeatures(): Collection
    {
        return $this->features;
    }

    /**
     * Check if Pennant is available.
     */
    protected function isPennantAvailable(): bool
    {
        return class_exists(\Laravel\Pennant\Feature::class);
    }

    /**
     * Check access via Pennant.
     */
    protected function checkPennantAccess(string $featureName): bool
    {
        return \Laravel\Pennant\Feature::for($this)->active($featureName);
    }

    /**
     * Activate a feature via Pennant.
     */
    protected function activatePennantFeature(string $featureName): void
    {
        \Laravel\Pennant\Feature::for($this)->activate($featureName);
    }

    /**
     * Deactivate a feature via Pennant.
     */
    protected function deactivatePennantFeature(string $featureName): void
    {
        \Laravel\Pennant\Feature::for($this)->deactivate($featureName);
    }

    /**
     * Forget a feature via Pennant.
     */
    protected function forgetPennantFeature(string $featureName): void
    {
        \Laravel\Pennant\Feature::for($this)->forget($featureName);
    }

    /**
     * Get the features query with context filtering.
     *
     * @return \Illuminate\Database\Eloquent\Builder<FeatureContract&Model>
     */
    protected function getFeaturesQuery(
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = $this->features()->getQuery();

        if (config('mandate.context.subject_features', false)) {
            $pivotTable = config('mandate.tables.subject_features', 'mandate_subject_features');
            $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

            if ($scope !== null) {
                $query->where("{$pivotTable}.scope", $scope);
            }

            if ($contextModel instanceof Model) {
                $query->where("{$pivotTable}.{$contextMorphName}_type", $contextModel->getMorphClass())
                    ->where("{$pivotTable}.{$contextMorphName}_id", $contextModel->getKey());
            } elseif ($contextModel !== null) {
                $query->where("{$pivotTable}.{$contextMorphName}_type", $contextModel);
            }
        }

        return $query;
    }

    /**
     * Resolve a feature to its name.
     */
    protected function resolveFeatureName(string|FeatureContract $feature): string
    {
        if ($feature instanceof FeatureContract) {
            return $feature->getAttribute('name');
        }

        return $feature;
    }

    /**
     * Resolve features to an array of names.
     *
     * @param  string|iterable<string|FeatureContract>|FeatureContract  $features
     * @return array<string>
     */
    protected function resolveFeatureNames(string|iterable|FeatureContract $features): array
    {
        if ($features instanceof FeatureContract) {
            return [$features->getAttribute('name')];
        }

        if (is_string($features)) {
            return [$features];
        }

        $names = [];
        foreach ($features as $feature) {
            $names[] = $this->resolveFeatureName($feature);
        }

        return $names;
    }
}
