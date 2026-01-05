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
use OffloadProject\Mandate\Support\ModelScope;

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
     * Get a fluent API for a specific model.
     */
    public function for(Model $model): ModelScope
    {
        return new ModelScope($model);
    }

    /**
     * Check if a model can perform an action (permission + feature check).
     */
    public function can(Model $model, string $permission): bool
    {
        return $this->permissions->can($model, $permission);
    }

    /**
     * Check if a model has been assigned a role (role + feature check).
     */
    public function assignedRole(Model $model, string $role): bool
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
     * Enable a feature for a scope (model or string).
     */
    public function enableFeature(Model|string $scope, string $feature): void
    {
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        \Laravel\Pennant\Feature::for($scope)->activate($feature);
    }

    /**
     * Disable a feature for a scope (model or string).
     */
    public function disableFeature(Model|string $scope, string $feature): void
    {
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        \Laravel\Pennant\Feature::for($scope)->deactivate($feature);
    }

    /**
     * Enable a feature for everyone.
     */
    public function enableForAll(string $feature): void
    {
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        \Laravel\Pennant\Feature::activateForEveryone($feature);
    }

    /**
     * Disable a feature for everyone.
     */
    public function disableForAll(string $feature): void
    {
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        \Laravel\Pennant\Feature::deactivateForEveryone($feature);
    }

    /**
     * Purge stored feature values.
     *
     * @param  string|array<string>  $features
     */
    public function purgeFeature(string|array $features): void
    {
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        \Laravel\Pennant\Feature::purge($features);
    }

    /**
     * Forget feature value for a scope.
     */
    public function forgetFeature(Model|string $scope, string $feature): void
    {
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        \Laravel\Pennant\Feature::for($scope)->forget($feature);
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
            $this->getPermissionSyncColumns(),
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
            $this->getRoleSyncColumns(),
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
     * Sync all discovered features to the database.
     *
     * @return array{created: int, existing: int, updated: int}
     */
    public function syncFeatures(): array
    {
        if (! config('mandate.features.enabled', true)) {
            return ['created' => 0, 'existing' => 0, 'updated' => 0];
        }

        return $this->syncer->syncFeatures(
            $this->features->all(),
            $this->getFeatureSyncColumns(),
        );
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
     * Sync everything including features.
     *
     * @return array{permissions: array, roles: array, features: array}
     */
    public function syncAll(?string $guard = null, bool $seed = false): array
    {
        $result = $this->sync($guard, $seed);
        $result['features'] = $this->syncFeatures();

        return $result;
    }

    /**
     * Sync feature-role associations from config.
     *
     * @param  string|null  $scope  The scope for feature-scoped roles (defaults to 'feature')
     * @param  string|null  $contextModelType  The context model type (defaults to feature class)
     * @return array{assigned: int}
     */
    public function syncFeatureRoles(
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array {
        if (! config('mandate.features.enabled', true)) {
            return ['assigned' => 0];
        }

        $featureRolesConfig = config('mandate-seed.feature_roles', []);

        return $this->syncer->syncFeatureRoles($featureRolesConfig, $guard, $seed, $scope, $contextModelType);
    }

    /**
     * Sync feature-permission associations from config.
     *
     * @param  string|null  $scope  The scope for feature-scoped permissions (defaults to 'feature')
     * @param  string|null  $contextModelType  The context model type (defaults to feature class)
     * @return array{granted: int}
     */
    public function syncFeaturePermissions(
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array {
        if (! config('mandate.features.enabled', true)) {
            return ['granted' => 0];
        }

        $featurePermissionsConfig = config('mandate-seed.feature_permissions', []);

        return $this->syncer->syncFeaturePermissions($featurePermissionsConfig, $guard, $seed, $scope, $contextModelType);
    }

    /**
     * Sync all feature associations (roles and permissions) from config.
     *
     * @param  string|null  $scope  The scope for feature-scoped items (defaults to 'feature')
     * @param  string|null  $contextModelType  The context model type (defaults to feature class)
     * @return array{roles: array{assigned: int}, permissions: array{granted: int}}
     */
    public function syncFeatureAssociations(
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array {
        return [
            'roles' => $this->syncFeatureRoles($guard, $seed, $scope, $contextModelType),
            'permissions' => $this->syncFeaturePermissions($guard, $seed, $scope, $contextModelType),
        ];
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
     * Get the columns to sync for permissions.
     *
     * @return array<string>
     */
    private function getPermissionSyncColumns(): array
    {
        return config('mandate.sync_columns.permissions', ['set', 'label', 'description']);
    }

    /**
     * Get the columns to sync for roles.
     *
     * @return array<string>
     */
    private function getRoleSyncColumns(): array
    {
        return config('mandate.sync_columns.roles', ['set', 'label', 'description']);
    }

    /**
     * Get the columns to sync for features.
     *
     * @return array<string>
     */
    private function getFeatureSyncColumns(): array
    {
        return config('mandate.sync_columns.features', ['label', 'description']);
    }
}
