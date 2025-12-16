<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Events\MandateSynced;
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;

/**
 * Main Mandate service for managing feature-flag aware permissions and roles.
 */
final class MandateManager
{
    public function __construct(
        private readonly FeatureRegistryContract $features,
        private readonly PermissionRegistryContract $permissions,
        private readonly RoleRegistryContract $roleRegistry,
        private readonly DatabaseSyncer $syncer,
    ) {}

    /**
     * Get the feature registry.
     */
    public function features(): FeatureRegistryContract
    {
        return $this->features;
    }

    /**
     * Get the permission registry.
     */
    public function permissions(): PermissionRegistryContract
    {
        return $this->permissions;
    }

    /**
     * Get the role registry.
     */
    public function roles(): RoleRegistryContract
    {
        return $this->roleRegistry;
    }

    /**
     * Get a specific feature with its permissions and roles.
     */
    public function feature(string $class): ?FeatureData
    {
        return $this->features->find($class);
    }

    /**
     * Get a specific permission.
     */
    public function permission(string $permission): ?PermissionData
    {
        return $this->permissions->find($permission);
    }

    /**
     * Get a specific role.
     */
    public function role(string $role): ?RoleData
    {
        return $this->roleRegistry->find($role);
    }

    /**
     * Check if a model can perform an action (permission + feature check).
     */
    public function can(Model $model, string $permission): bool
    {
        return $this->permissions->can($model, $permission);
    }

    /**
     * Check if a model has a role (role + feature check).
     */
    public function hasRole(Model $model, string $role): bool
    {
        return $this->roleRegistry->has($model, $role);
    }

    /**
     * Get all granted permissions for a model.
     *
     * @return Collection<int, PermissionData>
     */
    public function grantedPermissions(Model $model): Collection
    {
        return $this->permissions->granted($model);
    }

    /**
     * Get all assigned roles for a model.
     *
     * @return Collection<int, RoleData>
     */
    public function assignedRoles(Model $model): Collection
    {
        return $this->roleRegistry->assigned($model);
    }

    /**
     * Get all available permissions for a model (feature is active).
     *
     * @return Collection<int, PermissionData>
     */
    public function availablePermissions(Model $model): Collection
    {
        return $this->permissions->available($model);
    }

    /**
     * Get all available roles for a model (feature is active).
     *
     * @return Collection<int, RoleData>
     */
    public function availableRoles(Model $model): Collection
    {
        return $this->roleRegistry->available($model);
    }

    /**
     * Sync all discovered permissions to the database.
     *
     * @return array{created: int, existing: int, updated: int}
     */
    public function syncPermissions(?string $guard = null): array
    {
        $result = $this->syncer->syncPermissions(
            $this->permissions->all(),
            $this->getSyncColumns(),
            $guard,
        );

        PermissionsSynced::dispatch(
            $result['created'],
            $result['existing'],
            $result['updated'],
            $guard,
        );

        return $result;
    }

    /**
     * Sync all discovered roles to the database.
     *
     * By default (seed=false), only creates new roles without modifying
     * existing role-permission relationships. This preserves any permissions
     * assigned via UI/database.
     *
     * When seed=true, permissions from config are synced to roles. This should
     * typically only be used for initial setup or when intentionally resetting
     * role permissions to match config.
     *
     * @return array{created: int, existing: int, updated: int, permissions_synced: int}
     */
    public function syncRoles(?string $guard = null, bool $seed = false): array
    {
        $result = $this->syncer->syncRoles(
            $this->roleRegistry->all(),
            $this->getSyncColumns(),
            $guard,
            $seed,
        );

        RolesSynced::dispatch(
            $result['created'],
            $result['existing'],
            $result['updated'],
            $result['permissions_synced'],
            $guard,
            $seed,
        );

        return $result;
    }

    /**
     * Sync both permissions and roles.
     *
     * @return array{permissions: array{created: int, existing: int, updated: int}, roles: array{created: int, existing: int, updated: int, permissions_synced: int}}
     */
    public function sync(?string $guard = null, bool $seed = false): array
    {
        $permissions = $this->syncPermissions($guard);
        $roles = $this->syncRoles($guard, $seed);

        MandateSynced::dispatch($permissions, $roles, $guard, $seed);

        return compact('permissions', 'roles');
    }

    /**
     * Clear all cached data.
     */
    public function clearCache(): void
    {
        $this->features->clearCache();
        $this->permissions->clearCache();
        $this->roleRegistry->clearCache();
    }

    /**
     * Get the columns to sync based on config.
     *
     * @return array<string>
     *
     * @deprecated The 'store_set_in_database' config key is deprecated. Use 'sync_columns' instead.
     */
    private function getSyncColumns(): array
    {
        $config = config('mandate.sync_columns', false);

        // Legacy support: check old config key
        // @deprecated Remove in v2.0
        if ($config === false) {
            $config = config('mandate.store_set_in_database', false);
            if ($config === true) {
                trigger_error(
                    'The "mandate.store_set_in_database" config key is deprecated. Use "mandate.sync_columns" instead.',
                    E_USER_DEPRECATED
                );

                return ['set'];
            }
        }

        if ($config === true) {
            return ['set', 'label', 'description'];
        }

        if (is_array($config)) {
            return $config;
        }

        return [];
    }
}
