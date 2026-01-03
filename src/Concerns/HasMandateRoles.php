<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Feature-aware roles and permissions trait.
 *
 * This trait wraps Spatie's HasRoles trait, adding feature-flag awareness
 * to permission and role checks with a cleaner API:
 *
 * Checking:
 * - holdsPermission, holdsAnyPermission, holdsAllPermissions
 * - holdsRole, holdsAnyRole, holdsAllRoles
 *
 * Assignment:
 * - grantRole, revokeRole
 * - grantPermission, revokePermission
 *
 * Spatie's wildcard permissions are fully supported.
 */
trait HasMandateRoles
{
    use HasRoles {
        // Checking methods
        HasRoles::hasPermissionTo as spatieHasPermissionTo;
        HasRoles::hasAnyPermission as spatieHasAnyPermission;
        HasRoles::hasAllPermissions as spatieHasAllPermissions;
        HasRoles::hasRole as spatieHasRole;
        HasRoles::hasAnyRole as spatieHasAnyRole;
        HasRoles::hasAllRoles as spatieHasAllRoles;
        // Assignment methods
        HasRoles::assignRole as spatieAssignRole;
        HasRoles::removeRole as spatieRemoveRole;
        HasRoles::givePermissionTo as spatieGivePermissionTo;
        HasRoles::revokePermissionTo as spatieRevokePermissionTo;
        HasRoles::syncRoles as spatieSyncRoles;
        HasRoles::syncPermissions as spatieSyncPermissions;
    }

    // =========================================================================
    // Permission Checks (feature-aware)
    // =========================================================================

    /**
     * Check if the model holds a permission (feature-aware).
     *
     * @param  string|Permission  $permission
     */
    public function holdsPermission($permission, ?string $guardName = null): bool
    {
        if (! $this->spatieHasPermissionTo($permission, $guardName)) {
            return false;
        }

        $permissionName = is_string($permission) ? $permission : $permission->name;
        $permissionRegistry = app(PermissionRegistryContract::class);
        $permissionData = $permissionRegistry->find($permissionName);

        if ($permissionData === null || $permissionData->feature === null) {
            return true;
        }

        return $this->isMandateFeatureActive($permissionData->feature);
    }

    /**
     * Check if the model holds any of the given permissions (feature-aware).
     *
     * @param  string|int|array|Permission|Collection  ...$permissions
     */
    public function holdsAnyPermission(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        foreach ($permissions as $permission) {
            if ($this->holdsPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model holds all of the given permissions (feature-aware).
     *
     * Returns false if no permissions are provided (empty input).
     *
     * @param  string|int|array|Permission|Collection  ...$permissions
     */
    public function holdsAllPermissions(...$permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        if ($permissions->isEmpty()) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $this->holdsPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Role Checks (feature-aware)
    // =========================================================================

    /**
     * Check if the model holds a role (feature-aware).
     *
     * @param  string|int|array|Role|Collection  $roles
     */
    public function holdsRole($roles, ?string $guard = null): bool
    {
        if (is_int($roles)) {
            $roleModel = $this->getRoleClass()::findById($roles, $guard ?? $this->getDefaultGuardName());
            if ($roleModel === null) {
                return false;
            }

            return $this->hasFeatureAwareRole($roleModel->name, $guard);
        }

        if (is_string($roles) || $roles instanceof Role) {
            $roleName = is_string($roles) ? $roles : $roles->name;

            return $this->hasFeatureAwareRole($roleName, $guard);
        }

        // For arrays/collections, check if model has ANY of the roles
        $roles = collect($roles)->map(fn ($role) => is_string($role) ? $role : $role->name);

        foreach ($roles as $role) {
            if ($this->hasFeatureAwareRole($role, $guard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model holds any of the given roles (feature-aware).
     *
     * @param  string|int|array|Role|Collection  ...$roles
     */
    public function holdsAnyRole(...$roles): bool
    {
        return $this->holdsRole(collect($roles)->flatten());
    }

    /**
     * Check if the model holds all the given roles (feature-aware).
     *
     * Returns false if no roles are provided (empty input).
     *
     * @param  string|int|array|Role|Collection  $roles
     */
    public function holdsAllRoles($roles, ?string $guard = null): bool
    {
        $roles = collect($roles)->flatten()->map(
            fn ($role) => is_string($role) ? $role : $role->name
        );

        if ($roles->isEmpty()) {
            return false;
        }

        foreach ($roles as $role) {
            if (! $this->hasFeatureAwareRole($role, $guard)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Role Assignment
    // =========================================================================

    /**
     * Grant one or more roles to the model.
     *
     * @param  array|string|int|Role|Collection  ...$roles
     * @return $this
     */
    public function grantRole(...$roles): static
    {
        return $this->spatieAssignRole(...$roles);
    }

    /**
     * Revoke a role from the model.
     *
     * @param  string|int|Role  $role
     * @return $this
     */
    public function revokeRole($role): static
    {
        return $this->spatieRemoveRole($role);
    }

    // =========================================================================
    // Permission Assignment
    // =========================================================================

    /**
     * Grant one or more permissions to the model.
     *
     * @param  string|int|array|Permission|Collection  ...$permissions
     * @return $this
     */
    public function grantPermission(...$permissions): static
    {
        return $this->spatieGivePermissionTo(...$permissions);
    }

    /**
     * Revoke a permission from the model.
     *
     * @param  string|int|array|Permission|Collection  $permission
     * @return $this
     */
    public function revokePermission($permission): static
    {
        return $this->spatieRevokePermissionTo($permission);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Check a single role with feature awareness.
     */
    private function hasFeatureAwareRole(string $roleName, ?string $guard = null): bool
    {
        if (! $this->spatieHasRole($roleName, $guard)) {
            return false;
        }

        $roleRegistry = app(RoleRegistryContract::class);
        $roleData = $roleRegistry->find($roleName);

        if ($roleData === null || $roleData->feature === null) {
            return true;
        }

        return $this->isMandateFeatureActive($roleData->feature);
    }

    /**
     * Check if a feature is active for this model.
     *
     * If Laravel Pennant is not installed, treats the feature as inactive
     * to avoid fatal errors (Pennant is optional).
     */
    private function isMandateFeatureActive(string $feature): bool
    {
        if (! class_exists(Feature::class)) {
            return false;
        }

        return Feature::for($this)->active($feature);
    }
}
