<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Handles syncing permissions and roles to the database.
 */
final class DatabaseSyncer
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

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
        $permissionClass = $this->registrar->getPermissionClass();

        foreach ($permissions as $permission) {
            $guardName = $permission->guard ?? $guard ?? config('auth.defaults.guard');

            $spatiePermission = $permissionClass::where('name', $permission->name)
                ->where('guard_name', $guardName)
                ->first();

            if ($spatiePermission) {
                $existing++;

                if (! empty($syncColumns)) {
                    $changed = $this->updateModelColumns(
                        $spatiePermission,
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

                $permissionClass::create($data);
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
        $roleClass = $this->registrar->getRoleClass();

        foreach ($roles as $role) {
            $guardName = $role->guard ?? $guard ?? config('auth.defaults.guard');

            $spatieRole = $roleClass::where('name', $role->name)
                ->where('guard_name', $guardName)
                ->first();

            if ($spatieRole) {
                $existing++;

                if (! empty($syncColumns)) {
                    $changed = $this->updateModelColumns(
                        $spatieRole,
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

                $spatieRole = $roleClass::create($data);
                $created++;

                // For newly created roles, always seed permissions (direct + inherited)
                $allPermissions = $role->allPermissions();
                if (! empty($allPermissions)) {
                    $spatieRole->syncPermissions($allPermissions);
                    $permissionsSynced += count($allPermissions);
                }
            }

            // Only sync permissions for existing roles when explicitly seeding
            if ($seed && $spatieRole->wasRecentlyCreated === false) {
                $allPermissions = $role->allPermissions();
                if (! empty($allPermissions)) {
                    $spatieRole->syncPermissions($allPermissions);
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
}
