<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Support\WildcardMatcher;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Feature-aware roles and permissions trait.
 *
 * This trait wraps Spatie's HasRoles trait, routing permission and role
 * checks through Mandate's feature-flag aware system while keeping all
 * assignment methods (givePermissionTo, assignRole, etc.) unchanged.
 *
 * Use this trait instead of Spatie's HasRoles for feature-aware authorization.
 */
trait HasMandateRoles
{
    use HasPermissions {
        HasPermissions::hasPermissionTo as spatieHasPermissionTo;
    }
    use HasRoles {
        HasRoles::hasRole as spatieHasRole;
        HasRoles::hasAnyRole as spatieHasAnyRole;
        HasRoles::hasAllRoles as spatieHasAllRoles;

        // Avoid conflict - HasRoles uses HasPermissions internally
        HasRoles::hasPermissionTo as private spatieHasPermissionToFromRoles;
    }

    /**
     * Check if the model has a permission (feature-aware).
     *
     * Returns true only if:
     * 1. The model has the permission via Spatie
     * 2. The permission's feature flag is active (if gated by a feature)
     *
     * @param  string|Permission  $permission
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $permissionName = is_string($permission) ? $permission : $permission->name;

        // Handle wildcard patterns
        if (WildcardMatcher::isWildcard($permissionName)) {
            return $this->hasWildcardPermission($permissionName);
        }

        // Check if user has the permission via Spatie
        if (! $this->spatieHasPermissionTo($permission, $guardName)) {
            return false;
        }

        // Check if permission is gated by a feature
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

        // For arrays/collections, check if model has any of the roles
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
     * Check if the model has any permission matching a wildcard pattern.
     */
    private function hasWildcardPermission(string $pattern): bool
    {
        $permissionRegistry = app(PermissionRegistryContract::class);

        // Get user's permissions once to avoid repeated lookups
        $userPermissionNames = $this->getAllPermissions()->pluck('name')->flip();

        foreach ($permissionRegistry->all() as $permissionData) {
            if (! WildcardMatcher::matches($pattern, $permissionData->name)) {
                continue;
            }

            // Check if user has this permission (O(1) lookup)
            if (! $userPermissionNames->has($permissionData->name)) {
                continue;
            }

            // Check feature flag if gated
            if ($permissionData->feature !== null) {
                if (! Feature::for($this)->active($permissionData->feature)) {
                    continue;
                }
            }

            // Found a matching, granted permission
            return true;
        }

        return false;
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
