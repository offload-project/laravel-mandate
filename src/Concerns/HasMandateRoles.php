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
 * to permission and role checks while keeping all assignment methods
 * (givePermissionTo, assignRole, etc.) unchanged.
 *
 * Spatie's wildcard permissions are fully supported - this trait just adds
 * the feature gate check on top of Spatie's result.
 *
 * Use this trait instead of Spatie's HasRoles for feature-aware authorization.
 */
trait HasMandateRoles
{
    use HasRoles {
        HasRoles::hasPermissionTo as spatieHasPermissionTo;
        HasRoles::hasRole as spatieHasRole;
        HasRoles::hasAnyRole as spatieHasAnyRole;
        HasRoles::hasAllRoles as spatieHasAllRoles;
    }

    /**
     * Check if the model has a permission (feature-aware).
     *
     * Returns true only if:
     * 1. The model has the permission via Spatie (including wildcard matching)
     * 2. The permission's feature flag is active (if gated by a feature)
     *
     * @param  string|Permission  $permission
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        // Let Spatie handle all permission logic including wildcards
        if (! $this->spatieHasPermissionTo($permission, $guardName)) {
            return false;
        }

        // Check if permission is gated by a feature
        $permissionName = is_string($permission) ? $permission : $permission->name;
        $permissionRegistry = app(PermissionRegistryContract::class);
        $permissionData = $permissionRegistry->find($permissionName);

        if ($permissionData === null || $permissionData->feature === null) {
            // Not in registry or not gated - permission check passed
            return true;
        }

        // Check if the feature is active for this model
        return Feature::for($this)->active($permissionData->feature);
    }

    /**
     * Check if the model has a role (feature-aware).
     *
     * Returns true only if:
     * 1. The model has the role via Spatie
     * 2. The role's feature flag is active (if gated by a feature)
     *
     * @param  string|int|array|Role|Collection  $roles
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        if (is_int($roles)) {
            // Resolve ID to role name for consistent feature-aware behavior
            $roleModel = $this->getRoleClass()::find($roles);
            if ($roleModel === null) {
                return false;
            }

            return $this->hasFeatureAwareRole($roleModel->name, $guard);
        }

        if (is_string($roles) || $roles instanceof Role) {
            $roleName = is_string($roles) ? $roles : $roles->name;

            return $this->hasFeatureAwareRole($roleName, $guard);
        }

        // For arrays/collections, check if model has ANY of the roles (matches Spatie's behavior)
        $roles = collect($roles)->map(fn ($role) => is_string($role) ? $role : $role->name);

        foreach ($roles as $role) {
            if ($this->hasFeatureAwareRole($role, $guard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has any of the given roles (feature-aware).
     *
     * @param  string|int|array|Role|Collection  $roles
     */
    public function hasAnyRole(...$roles): bool
    {
        $roles = collect($roles)->flatten();

        return $this->hasRole($roles);
    }

    /**
     * Check if the model has all of the given roles (feature-aware).
     *
     * @param  string|int|array|Role|Collection  $roles
     */
    public function hasAllRoles($roles, ?string $guard = null): bool
    {
        $roles = collect($roles)->flatten()->map(
            fn ($role) => is_string($role) ? $role : $role->name
        );

        foreach ($roles as $role) {
            if (! $this->hasFeatureAwareRole($role, $guard)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check a single role with feature awareness.
     */
    private function hasFeatureAwareRole(string $roleName, ?string $guard = null): bool
    {
        // Check if user has the role via Spatie
        if (! $this->spatieHasRole($roleName, $guard)) {
            return false;
        }

        // Check if role is gated by a feature
        $roleRegistry = app(RoleRegistryContract::class);
        $roleData = $roleRegistry->find($roleName);

        if ($roleData === null || $roleData->feature === null) {
            // Not in registry or not gated - role check passed
            return true;
        }

        // Check if the feature is active for this model
        return Feature::for($this)->active($roleData->feature);
    }
}
