<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Data\FeatureData;

/**
 * Null implementation of FeatureRegistryContract.
 *
 * Used when Hoist (feature discovery) is not available.
 * Returns empty collections and false for all feature checks.
 */
final class NullFeatureRegistry implements FeatureRegistryContract
{
    /**
     * @return Collection<int, FeatureData>
     */
    public function all(): Collection
    {
        return collect();
    }

    /**
     * @return Collection<int, FeatureData>
     */
    public function forModel(Model $model): Collection
    {
        return collect();
    }

    public function find(string $class): ?FeatureData
    {
        return null;
    }

    /**
     * @return Collection<int, \OffloadProject\Mandate\Data\PermissionData>
     */
    public function permissions(string $class): Collection
    {
        return collect();
    }

    /**
     * @return Collection<int, \OffloadProject\Mandate\Data\RoleData>
     */
    public function roles(string $class): Collection
    {
        return collect();
    }

    public function isActive(Model $model, string $class): bool
    {
        return false;
    }

    public function clearCache(): void
    {
        // No-op for null implementation
    }
}
