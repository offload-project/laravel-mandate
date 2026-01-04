<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use OffloadProject\Mandate\Contracts\FeatureContract;
use OffloadProject\Mandate\Models\Concerns\HasContextScope;

final class Feature extends Model implements FeatureContract
{
    use HasContextScope;

    /** @var array<int, string> */
    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('mandate.tables.features', 'mandate_features'));
    }

    /**
     * Find a feature by name.
     */
    public static function findByName(string $name, ?string $scope = null): ?FeatureContract
    {
        $query = self::query()->where('name', $name);

        if ($scope !== null) {
            $query->where('scope', $scope);
        } else {
            $query->whereNull('scope');
        }

        /** @var FeatureContract|null */
        return $query->first();
    }

    /**
     * Find a feature by ID.
     */
    public static function findById(int|string $id): ?FeatureContract
    {
        /** @var FeatureContract|null */
        return self::query()->find($id);
    }

    /**
     * Create a new feature.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createFeature(array $attributes): FeatureContract
    {
        /** @var FeatureContract */
        return self::query()->create($attributes);
    }

    /**
     * Get the subjects (users, etc.) that have this feature.
     */
    public function subjects(string $subjectType): MorphToMany
    {
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');

        return $this->morphedByMany(
            $subjectType,
            $subjectMorphKey,
            config('mandate.tables.subject_features', 'mandate_subject_features'),
            config('mandate.columns.pivot_feature_key', 'feature_id'),
            "{$subjectMorphKey}_id"
        );
    }

    /**
     * Get the resolution value for this feature.
     */
    public function getValue(): mixed
    {
        $value = $this->getAttribute('value');

        if ($value === null) {
            return null;
        }

        // Try to decode as JSON
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Return as-is if not JSON
        return $value;
    }

    /**
     * Set the resolution value for this feature.
     */
    public function setValue(mixed $value): static
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $this->setAttribute('value', $value);

        return $this;
    }

    /**
     * Check if the feature is active (value is truthy).
     */
    public function isActive(): bool
    {
        $value = $this->getValue();

        return (bool) $value;
    }
}
