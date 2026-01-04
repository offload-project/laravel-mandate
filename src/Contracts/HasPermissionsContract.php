<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Contract for models that have permissions.
 */
interface HasPermissionsContract
{
    /**
     * Get the permissions relationship.
     */
    public function permissions(): MorphToMany;

    /**
     * Check if the model has been granted a specific permission.
     */
    public function granted(
        string|PermissionContract $permission,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Check if the model has been granted any of the given permissions.
     *
     * @param  iterable<string|PermissionContract>  $permissions
     */
    public function grantedAnyPermission(
        iterable $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Check if the model has been granted all of the given permissions.
     *
     * @param  iterable<string|PermissionContract>  $permissions
     */
    public function grantedAllPermissions(
        iterable $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Grant permissions to the model.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function grant(
        string|iterable|PermissionContract $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): static;

    /**
     * Revoke permissions from the model.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function revoke(string|iterable|PermissionContract $permissions): static;

    /**
     * Get all permission names for the model.
     *
     * @return array<string>
     */
    public function permissionNames(): array;

    /**
     * Get all permissions for the model (direct + through roles).
     *
     * @return Collection<int, PermissionContract>
     */
    public function allPermissions(): Collection;
}
