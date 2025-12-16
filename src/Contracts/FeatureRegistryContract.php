<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;

/**
 * Contract for feature registry implementations.
 */
interface FeatureRegistryContract
{
    /**
     * Get all discovered features with their permissions and roles.
     *
     * @return Collection<int, FeatureData>
     */
    public function all(): Collection;

    /**
     * Get features with active status for a specific model.
     *
     * @return Collection<int, FeatureData>
     */
    public function forModel(Model $model): Collection;

    /**
     * Get a specific feature by class name.
     */
    public function find(string $class): ?FeatureData;

    /**
     * Get permissions for a specific feature.
     *
     * @return Collection<int, PermissionData>
     */
    public function permissions(string $class): Collection;

    /**
     * Get roles for a specific feature.
     *
     * @return Collection<int, RoleData>
     */
    public function roles(string $class): Collection;

    /**
     * Clear the cached features.
     */
    public function clearCache(): void;
}
