<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\PermissionContract;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Support\WildcardMatcher;

/**
 * Provides permission functionality for models.
 *
 * @mixin Model
 */
trait HasPermissions
{
    /**
     * Get the permissions relationship.
     */
    public function permissions(): MorphToMany
    {
        /** @var class-string<PermissionContract&Model> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');
        $contextEnabled = config('mandate.context.subject_permissions', false);

        $relation = $this->morphToMany(
            $permissionClass,
            $subjectMorphKey,
            config('mandate.tables.subject_permissions', 'mandate_subject_permissions'),
            "{$subjectMorphKey}_id",
            config('mandate.columns.pivot_permission_key', 'permission_id')
        );

        if ($contextEnabled) {
            $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
            $relation->withPivot(['scope', "{$contextMorphName}_type", "{$contextMorphName}_id"]);
        }

        return $relation->withTimestamps();
    }

    /**
     * Check if the model has been granted a specific permission.
     */
    public function granted(
        string|PermissionContract $permission,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        $permissionName = $this->resolvePermissionName($permission);

        // Check with wildcards if enabled
        if (config('mandate.wildcards', false)) {
            return $this->grantedPermissionWithWildcard($permissionName, $scope, $contextModel);
        }

        return $this->getPermissionsQuery($scope, $contextModel)
            ->where('name', $permissionName)
            ->exists();
    }

    /**
     * Check if the model has been granted any of the given permissions.
     *
     * @param  iterable<string|PermissionContract>  $permissions
     */
    public function grantedAnyPermission(
        iterable $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        foreach ($permissions as $permission) {
            if ($this->granted($permission, $scope, $contextModel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has been granted all of the given permissions.
     *
     * @param  iterable<string|PermissionContract>  $permissions
     */
    public function grantedAllPermissions(
        iterable $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        foreach ($permissions as $permission) {
            if (! $this->granted($permission, $scope, $contextModel)) {
                return false;
            }
        }

        return true;
    }

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
    ): static {
        $resolved = $this->resolvePermissions($permissions);
        $pivotData = $this->buildContextPivotData($scope, $contextModel);

        $syncData = [];
        foreach ($resolved as $permission) {
            $syncData[$permission->getKey()] = $pivotData;
        }

        $this->permissions()->syncWithoutDetaching($syncData);

        return $this;
    }

    /**
     * Revoke permissions from the model.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function revoke(string|iterable|PermissionContract $permissions): static
    {
        $resolved = $this->resolvePermissions($permissions);

        $this->permissions()->detach($resolved->pluck('id'));

        return $this;
    }

    /**
     * Sync permissions for the model.
     *
     * @param  iterable<string|PermissionContract>  $permissions
     * @return $this
     */
    public function syncPermissions(
        iterable $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): static {
        $resolved = $this->resolvePermissions($permissions);
        $pivotData = $this->buildContextPivotData($scope, $contextModel);

        $syncData = [];
        foreach ($resolved as $permission) {
            $syncData[$permission->getKey()] = $pivotData;
        }

        $this->permissions()->sync($syncData);

        return $this;
    }

    /**
     * Get all permission names for the model.
     *
     * @return array<string>
     */
    public function permissionNames(): array
    {
        return $this->allPermissions()->pluck('name')->all();
    }

    /**
     * Get all permissions for the model (direct only, override in HasRoles for role permissions).
     *
     * @return Collection<int, PermissionContract>
     */
    public function allPermissions(): Collection
    {
        return $this->permissions;
    }

    /**
     * Check permission with wildcard support.
     */
    protected function grantedPermissionWithWildcard(
        string $permissionName,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        $permissions = $this->getPermissionsQuery($scope, $contextModel)
            ->pluck('name')
            ->all();

        // Check for exact match
        if (in_array($permissionName, $permissions, true)) {
            return true;
        }

        // Check if any permission is a wildcard that matches
        foreach ($permissions as $grantedPermission) {
            if (WildcardMatcher::matches($grantedPermission, $permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the permissions query with context filtering.
     *
     * @return \Illuminate\Database\Eloquent\Builder<PermissionContract&Model>
     */
    protected function getPermissionsQuery(
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = $this->permissions()->getQuery();

        if (config('mandate.context.subject_permissions', false)) {
            $pivotTable = config('mandate.tables.subject_permissions', 'mandate_subject_permissions');
            $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');

            if ($scope !== null) {
                $query->where("{$pivotTable}.scope", $scope);
            }

            if ($contextModel instanceof Model) {
                $query->where("{$pivotTable}.{$contextMorphName}_type", $contextModel->getMorphClass())
                    ->where("{$pivotTable}.{$contextMorphName}_id", $contextModel->getKey());
            } elseif ($contextModel !== null) {
                $query->where("{$pivotTable}.{$contextMorphName}_type", $contextModel);
            }
        }

        return $query;
    }

    /**
     * Build pivot data for context columns.
     *
     * @return array<string, mixed>
     */
    protected function buildContextPivotData(
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): array {
        if (! config('mandate.context.subject_permissions', false)) {
            return [];
        }

        $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
        $data = ['scope' => $scope];

        if ($contextModel instanceof Model) {
            $data["{$contextMorphName}_type"] = $contextModel->getMorphClass();
            $data["{$contextMorphName}_id"] = $contextModel->getKey();
        } elseif ($contextModel !== null) {
            $data["{$contextMorphName}_type"] = $contextModel;
            $data["{$contextMorphName}_id"] = null;
        } else {
            $data["{$contextMorphName}_type"] = null;
            $data["{$contextMorphName}_id"] = null;
        }

        return $data;
    }

    /**
     * Resolve a permission to its name.
     */
    protected function resolvePermissionName(string|PermissionContract $permission): string
    {
        if ($permission instanceof PermissionContract) {
            return $permission->getAttribute('name');
        }

        return $permission;
    }

    /**
     * Resolve permissions to a collection of Permission models.
     *
     * @param  string|iterable<string|PermissionContract>|PermissionContract  $permissions
     * @return Collection<int, PermissionContract&Model>
     */
    protected function resolvePermissions(string|iterable|PermissionContract $permissions): Collection
    {
        if ($permissions instanceof PermissionContract) {
            return collect([$permissions]);
        }

        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        /** @var class-string<PermissionContract&Model> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        $resolved = collect();
        $guardName = $this->getGuardName();

        foreach ($permissions as $permission) {
            if ($permission instanceof PermissionContract) {
                $resolved->push($permission);
            } else {
                $model = $permissionClass::findByName($permission, $guardName);
                if ($model !== null) {
                    $resolved->push($model);
                }
            }
        }

        return $resolved;
    }

    /**
     * Get the guard name for this model.
     */
    protected function getGuardName(): string
    {
        return $this->guard_name ?? config('auth.defaults.guard');
    }
}
