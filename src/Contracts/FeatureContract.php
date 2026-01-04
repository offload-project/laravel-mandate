<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Contract for Feature model implementations.
 */
interface FeatureContract
{
    /**
     * Find a feature by name.
     */
    public static function findByName(string $name, ?string $scope = null): ?self;

    /**
     * Find a feature by ID.
     */
    public static function findById(int|string $id): ?self;

    /**
     * Create a new feature.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createFeature(array $attributes): self;

    /**
     * Get the subjects (users, etc.) that have this feature.
     */
    public function subjects(string $subjectType): MorphToMany;

    /**
     * Get the resolution value for this feature.
     */
    public function getValue(): mixed;
}
