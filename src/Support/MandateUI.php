<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Helper class for generating UI-friendly permission, role, and feature data.
 *
 * Usage:
 *   $auth = app(MandateUI::class)->auth($user);
 *   // Returns: ['permissions' => [...], 'roles' => [...], 'features' => [...]]
 *
 *   $permissions = app(MandateUI::class)->permissionsMap($user);
 *   // Returns: ['users.view' => true, 'users.create' => false, ...]
 */
final class MandateUI
{
    /**
     * Get complete auth data for a user.
     *
     * @return array{permissions: array<string>, roles: array<string>, features: array<string>}
     */
    public function auth(Model $user): array
    {
        return [
            'permissions' => $this->getPermissions($user),
            'roles' => $this->getRoles($user),
            'features' => $this->getFeatures($user),
        ];
    }

    /**
     * Get a map of all permissions with their grant status.
     *
     * @return array<string, bool>
     */
    public function permissionsMap(Model $user): array
    {
        $map = [];

        if (method_exists($user, 'allPermissions')) {
            $grantedPermissions = $user->allPermissions()->pluck('name')->all();
        } else {
            $grantedPermissions = [];
        }

        // Get all registered permissions
        $permissions = app(\OffloadProject\Mandate\Contracts\PermissionRegistryContract::class);

        foreach ($permissions->names() as $permission) {
            $map[$permission] = in_array($permission, $grantedPermissions, true);
        }

        return $map;
    }

    /**
     * Get all permission names for a user.
     *
     * @return array<string>
     */
    public function getPermissions(Model $user): array
    {
        if (method_exists($user, 'permissionNames')) {
            return $user->permissionNames();
        }

        if (method_exists($user, 'allPermissions')) {
            return $user->allPermissions()->pluck('name')->all();
        }

        return [];
    }

    /**
     * Get all role names for a user.
     *
     * @return array<string>
     */
    public function getRoles(Model $user): array
    {
        if (method_exists($user, 'roleNames')) {
            return $user->roleNames();
        }

        if (method_exists($user, 'allRoles')) {
            return $user->allRoles()->pluck('name')->all();
        }

        return [];
    }

    /**
     * Get all active feature names for a user.
     *
     * @return array<string>
     */
    public function getFeatures(Model $user): array
    {
        if (method_exists($user, 'featureNames')) {
            return $user->featureNames();
        }

        // If UsesFeatures trait is not used, try to get active features via registry
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return [];
        }

        $features = app(\OffloadProject\Mandate\Contracts\FeatureRegistryContract::class);
        $activeFeatures = [];

        foreach ($features->all() as $feature) {
            if (\Laravel\Pennant\Feature::for($user)->active($feature->class)) {
                $activeFeatures[] = $feature->name;
            }
        }

        return $activeFeatures;
    }

    /**
     * Get grouped data for UI display.
     *
     * @return array{roles: array<string, array<string>>, permissions: array<string, array<string>>, features: array<string, array<string>>}
     */
    public function grouped(): array
    {
        $permissions = app(\OffloadProject\Mandate\Contracts\PermissionRegistryContract::class);
        $roles = app(\OffloadProject\Mandate\Contracts\RoleRegistryContract::class);
        $features = app(\OffloadProject\Mandate\Contracts\FeatureRegistryContract::class);

        return [
            'permissions' => $permissions->grouped()->map(fn ($group) => $group->pluck('name')->all())->all(),
            'roles' => $roles->grouped()->map(fn ($group) => $group->pluck('name')->all())->all(),
            'features' => ['default' => $features->all()->pluck('name')->all()],
        ];
    }
}
