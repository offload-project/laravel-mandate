<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Data\RoleData;

/**
 * Contract for role registry implementations.
 */
interface RoleRegistryContract
{
    /**
     * Get all discovered roles.
     *
     * @return Collection<int, RoleData>
     */
    public function all(): Collection;

    /**
     * Get roles with active status for a specific model.
     *
     * @return Collection<int, RoleData>
     */
    public function forModel(Model $model): Collection;

    /**
     * Get assigned roles for a model (has role AND feature is active).
     *
     * @return Collection<int, RoleData>
     */
    public function assigned(Model $model): Collection;

    /**
     * Get available roles (not gated by feature or feature is active).
     *
     * @return Collection<int, RoleData>
     */
    public function available(Model $model): Collection;

    /**
     * Check if a model has a specific role (considering feature flags).
     */
    public function has(Model $model, string $role): bool;

    /**
     * Get all role names.
     *
     * @return array<string>
     */
    public function names(): array;

    /**
     * Get roles grouped by their set name.
     *
     * @return Collection<string, Collection<int, RoleData>>
     */
    public function grouped(): Collection;

    /**
     * Get a specific role by name.
     */
    public function find(string $role): ?RoleData;

    /**
     * Get the feature class that gates a role.
     */
    public function feature(string $role): ?string;

    /**
     * Clear the cached roles.
     */
    public function clearCache(): void;
}
