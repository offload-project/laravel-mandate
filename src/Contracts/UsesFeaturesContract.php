<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Contract for models that use feature flags.
 */
interface UsesFeaturesContract
{
    /**
     * Get the features relationship.
     */
    public function features(): MorphToMany;

    /**
     * Check if the model has access to a specific feature.
     * Checks both that the feature is active globally AND active for this model.
     */
    public function hasAccess(
        string|FeatureContract $feature,
        ?string $context = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Check if the model has access to any of the given features.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function hasAnyAccess(
        iterable $features,
        ?string $context = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Check if the model has access to all of the given features.
     *
     * @param  iterable<string|FeatureContract>  $features
     */
    public function hasAllAccess(
        iterable $features,
        ?string $context = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Enable a feature for this model.
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function enable(string|iterable|FeatureContract $features): static;

    /**
     * Disable a feature for this model.
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function disable(string|iterable|FeatureContract $features): static;

    /**
     * Forget a feature state for this model (resets to default resolution).
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function forget(string|iterable|FeatureContract $features): static;

    /**
     * Get all feature names for the model.
     *
     * @return array<string>
     */
    public function featureNames(): array;

    /**
     * Get all features for the model.
     *
     * @return Collection<int, FeatureContract>
     */
    public function allFeatures(): Collection;
}
