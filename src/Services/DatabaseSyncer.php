<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\FeatureContract;
use OffloadProject\Mandate\Contracts\PermissionContract;
use OffloadProject\Mandate\Contracts\RoleContract;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Models\Feature;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use ReflectionClass;
use ReflectionClassConstant;
use Throwable;

/**
 * Handles syncing permissions, roles, and features to the database.
 */
final class DatabaseSyncer implements \OffloadProject\Mandate\Contracts\DatabaseSyncerContract
{
    /**
     * Sync permissions to the database.
     *
     * @param  Collection<int, PermissionData>  $permissions
     * @param  array<string>  $syncColumns
     * @return array{created: int, existing: int, updated: int}
     */
    public function syncPermissions(
        Collection $permissions,
        array $syncColumns,
        ?string $guard = null,
    ): array {
        $created = 0;
        $existing = 0;
        $updated = 0;

        /** @var class-string<PermissionContract&Model> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        foreach ($permissions as $permission) {
            $guardName = $permission->guard ?? $guard ?? config('auth.defaults.guard');

            $existingPermission = $permissionClass::findByName($permission->name, $guardName);

            if ($existingPermission !== null) {
                $existing++;

                if (! empty($syncColumns)) {
                    $changed = $this->updateModelColumns(
                        $existingPermission,
                        $syncColumns,
                        fn (string $col) => $this->getPermissionColumnValue($permission, $col)
                    );

                    if ($changed) {
                        $updated++;
                    }
                }
            } else {
                $data = [
                    'name' => $permission->name,
                    'guard_name' => $guardName,
                ];

                foreach ($syncColumns as $column) {
                    $data[$column] = $this->getPermissionColumnValue($permission, $column);
                }

                $permissionClass::createPermission($data);
                $created++;
            }
        }

        return compact('created', 'existing', 'updated');
    }

    /**
     * Sync roles to the database.
     *
     * @param  Collection<int, RoleData>  $roles
     * @param  array<string>  $syncColumns
     * @return array{created: int, existing: int, updated: int, permissions_synced: int}
     */
    public function syncRoles(
        Collection $roles,
        array $syncColumns,
        ?string $guard = null,
        bool $seed = false,
    ): array {
        $created = 0;
        $existing = 0;
        $updated = 0;
        $permissionsSynced = 0;

        /** @var class-string<RoleContract&Model> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        foreach ($roles as $role) {
            $guardName = $role->guard ?? $guard ?? config('auth.defaults.guard');

            /** @var (RoleContract&Model)|null $existingRole */
            $existingRole = $roleClass::findByName($role->name, $guardName);

            if ($existingRole !== null) {
                $existing++;
                $wasRecentlyCreated = false;

                if (! empty($syncColumns)) {
                    $changed = $this->updateModelColumns(
                        $existingRole,
                        $syncColumns,
                        fn (string $col) => $this->getRoleColumnValue($role, $col)
                    );

                    if ($changed) {
                        $updated++;
                    }
                }
            } else {
                $data = [
                    'name' => $role->name,
                    'guard_name' => $guardName,
                ];

                foreach ($syncColumns as $column) {
                    $data[$column] = $this->getRoleColumnValue($role, $column);
                }

                $existingRole = $roleClass::createRole($data);
                $created++;
                $wasRecentlyCreated = true;

                // For newly created roles, always seed permissions (direct + inherited)
                $allPermissions = $role->allPermissions();
                if (! empty($allPermissions)) {
                    $this->syncRolePermissions($existingRole, $allPermissions, $guardName);
                    $permissionsSynced += count($allPermissions);
                }
            }

            // Only sync permissions for existing roles when explicitly seeding
            if ($seed && ! $wasRecentlyCreated) {
                $allPermissions = $role->allPermissions();
                if (! empty($allPermissions)) {
                    $this->syncRolePermissions($existingRole, $allPermissions, $guardName);
                    $permissionsSynced += count($allPermissions);
                }
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'updated' => $updated,
            'permissions_synced' => $permissionsSynced,
        ];
    }

    /**
     * Sync features to the database.
     *
     * @param  Collection<int, FeatureData>  $features
     * @param  array<string>  $syncColumns
     * @return array{created: int, existing: int, updated: int}
     */
    public function syncFeatures(
        Collection $features,
        array $syncColumns,
    ): array {
        $created = 0;
        $existing = 0;
        $updated = 0;

        /** @var class-string<FeatureContract&Model> $featureClass */
        $featureClass = config('mandate.models.feature', Feature::class);

        foreach ($features as $feature) {
            $existingFeature = $featureClass::findByName($feature->name);

            if ($existingFeature !== null) {
                $existing++;

                if (! empty($syncColumns)) {
                    $changed = $this->updateModelColumns(
                        $existingFeature,
                        $syncColumns,
                        fn (string $col) => $this->getFeatureColumnValue($feature, $col)
                    );

                    if ($changed) {
                        $updated++;
                    }
                }
            } else {
                $data = [
                    'name' => $feature->name,
                ];

                foreach ($syncColumns as $column) {
                    $data[$column] = $this->getFeatureColumnValue($feature, $column);
                }

                $featureClass::createFeature($data);
                $created++;
            }
        }

        return compact('created', 'existing', 'updated');
    }

    /**
     * Sync feature-role associations from config.
     *
     * Features act as subjects that can have roles assigned to them via subject_roles table.
     *
     * @param  array<string, array<string>>  $featureRolesConfig  Map of feature class/name => role names
     * @param  string|null  $scope  The scope for the assignment (defaults to 'feature')
     * @param  string|null  $contextModelType  The context model type for scoped assignments
     * @return array{assigned: int}
     */
    public function syncFeatureRoles(
        array $featureRolesConfig,
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array {
        $assigned = 0;

        /** @var class-string<FeatureContract&Model> $featureClass */
        $featureClass = config('mandate.models.feature', Feature::class);

        $guardName = $guard ?? config('auth.defaults.guard');

        foreach ($featureRolesConfig as $featureKey => $roleNames) {
            // Find the feature by name or class
            $featureName = is_string($featureKey) && class_exists($featureKey)
                ? $this->getFeatureNameFromClass($featureKey)
                : $featureKey;

            /** @var (FeatureContract&Model)|null $feature */
            $feature = $featureClass::findByName($featureName);
            if ($feature === null) {
                continue;
            }

            $resolvedRoles = $this->resolveRoleNames($roleNames, $guardName);

            if (! empty($resolvedRoles) && $seed) {
                // Use the HasRoles trait methods - Feature is a subject
                if (method_exists($feature, 'syncRoles')) {
                    $feature->syncRoles($resolvedRoles, $scope, $contextModelType);
                    $assigned += count($resolvedRoles);
                }
            }
        }

        return compact('assigned');
    }

    /**
     * Sync feature-permission associations from config.
     *
     * Features act as subjects that can have permissions granted to them via subject_permissions table.
     *
     * @param  array<string, array<string>>  $featurePermissionsConfig  Map of feature class/name => permission names
     * @param  string|null  $scope  The scope for the grant (defaults to 'feature')
     * @param  string|null  $contextModelType  The context model type for scoped grants
     * @return array{granted: int}
     */
    public function syncFeaturePermissions(
        array $featurePermissionsConfig,
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array {
        $granted = 0;

        /** @var class-string<FeatureContract&Model> $featureClass */
        $featureClass = config('mandate.models.feature', Feature::class);

        $guardName = $guard ?? config('auth.defaults.guard');

        foreach ($featurePermissionsConfig as $featureKey => $permissionNames) {
            // Find the feature by name or class
            $featureName = is_string($featureKey) && class_exists($featureKey)
                ? $this->getFeatureNameFromClass($featureKey)
                : $featureKey;

            /** @var (FeatureContract&Model)|null $feature */
            $feature = $featureClass::findByName($featureName);
            if ($feature === null) {
                continue;
            }

            $resolvedPermissions = $this->resolvePermissionNames($permissionNames, $guardName);

            if (! empty($resolvedPermissions) && $seed) {
                // Use the HasPermissions trait methods - Feature is a subject
                if (method_exists($feature, 'syncPermissions')) {
                    $feature->syncPermissions($resolvedPermissions, $scope, $contextModelType);
                    $granted += count($resolvedPermissions);
                }
            }
        }

        return compact('granted');
    }

    /**
     * Resolve role names from config (supports class references and strings).
     *
     * @param  array<string>  $roleNames
     * @return array<string>
     */
    private function resolveRoleNames(array $roleNames, string $guardName): array
    {
        $resolved = [];

        foreach ($roleNames as $roleName) {
            if (class_exists($roleName)) {
                // It's a class reference - get all constants
                $reflection = new ReflectionClass($roleName);
                $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

                foreach ($constants as $constant) {
                    $value = $constant->getValue();
                    if (is_string($value)) {
                        $resolved[] = $value;
                    }
                }
            } else {
                $resolved[] = $roleName;
            }
        }

        return array_unique($resolved);
    }

    /**
     * Resolve permission names from config (supports class references and strings).
     *
     * @param  array<string>  $permissionNames
     * @return array<string>
     */
    private function resolvePermissionNames(array $permissionNames, string $guardName): array
    {
        $resolved = [];

        foreach ($permissionNames as $permissionName) {
            if (class_exists($permissionName)) {
                // It's a class reference - get all constants
                $reflection = new ReflectionClass($permissionName);
                $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

                foreach ($constants as $constant) {
                    $value = $constant->getValue();
                    if (is_string($value)) {
                        $resolved[] = $value;
                    }
                }
            } else {
                $resolved[] = $permissionName;
            }
        }

        return array_unique($resolved);
    }

    /**
     * Get feature name from a feature class.
     */
    private function getFeatureNameFromClass(string $className): string
    {
        if (! class_exists($className)) {
            return $className;
        }

        // Check if the class has a NAME constant
        if (defined("{$className}::NAME")) {
            return constant("{$className}::NAME");
        }

        // Fall back to class basename as snake_case
        $basename = class_basename($className);

        return mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $basename) ?? $basename);
    }

    /**
     * Sync permissions for a role.
     *
     * @param  array<string>  $permissionNames
     */
    private function syncRolePermissions(RoleContract&Model $role, array $permissionNames, string $guardName): void
    {
        /** @var class-string<PermissionContract&Model> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        $permissionIds = [];
        foreach ($permissionNames as $name) {
            $permission = $permissionClass::findByName($name, $guardName);
            if ($permission !== null) {
                $permissionIds[] = $permission->getKey();
            }
        }

        if (method_exists($role, 'syncPermissions')) {
            // Use our Role model's syncPermissions method
            $role->permissions()->sync($permissionIds);
        }
    }

    /**
     * Update model columns if values have changed.
     *
     * @param  array<string>  $columns
     * @param  callable(string): ?string  $valueGetter
     */
    private function updateModelColumns(Model $model, array $columns, callable $valueGetter): bool
    {
        $changed = false;

        foreach ($columns as $column) {
            $value = $valueGetter($column);
            if ($this->shouldUpdateColumn($model, $column, $value)) {
                $model->{$column} = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $model->save();
        }

        return $changed;
    }

    /**
     * Check if a column should be updated.
     */
    private function shouldUpdateColumn(Model $model, string $column, ?string $newValue): bool
    {
        try {
            $currentValue = $model->getAttribute($column);

            return $currentValue !== $newValue;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the value for a permission column.
     */
    private function getPermissionColumnValue(PermissionData $permission, string $column): ?string
    {
        return match ($column) {
            'set' => $permission->set,
            'label' => $permission->label,
            'description' => $permission->description,
            'scope' => $permission->scope,
            default => null,
        };
    }

    /**
     * Get the value for a role column.
     */
    private function getRoleColumnValue(RoleData $role, string $column): ?string
    {
        return match ($column) {
            'set' => $role->set,
            'label' => $role->label,
            'description' => $role->description,
            'scope' => $role->scope,
            default => null,
        };
    }

    /**
     * Get the value for a feature column.
     */
    private function getFeatureColumnValue(FeatureData $feature, string $column): ?string
    {
        return match ($column) {
            'label' => $feature->label,
            'description' => $feature->description,
            default => null,
        };
    }
}
