<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Data\PermissionData;

/**
 * Contract for permission registry implementations.
 */
interface PermissionRegistryContract
{
    /**
     * Get all discovered permissions.
     *
     * @return Collection<int, PermissionData>
     */
    public function all(): Collection;

    /**
     * Get permissions with active status for a specific model.
     *
     * @return Collection<int, PermissionData>
     */
    public function forModel(Model $model): Collection;

    /**
     * Get granted permissions for a model (has permission AND feature is active).
     *
     * @return Collection<int, PermissionData>
     */
    public function granted(Model $model): Collection;

    /**
     * Get available permissions (not gated by feature or feature is active).
     *
     * @return Collection<int, PermissionData>
     */
    public function available(Model $model): Collection;

    /**
     * Check if a model has a specific permission (considering feature flags).
     */
    public function can(Model $model, string $permission): bool;

    /**
     * Get all permission names.
     *
     * @return array<string>
     */
    public function names(): array;

    /**
     * Get permissions grouped by their set name.
     *
     * @return Collection<string, Collection<int, PermissionData>>
     */
    public function grouped(): Collection;

    /**
     * Get a specific permission by name.
     */
    public function find(string $permission): ?PermissionData;

    /**
     * Get the feature class that gates a permission.
     */
    public function feature(string $permission): ?string;

    /**
     * Clear the cached permissions.
     */
    public function clearCache(): void;
}
