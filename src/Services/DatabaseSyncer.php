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
use Throwable;

/**
 * Handles syncing permissions, roles, and features to the database.
 */
final class DatabaseSyncer
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
