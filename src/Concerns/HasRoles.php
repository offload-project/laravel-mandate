<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\PermissionContract;
use OffloadProject\Mandate\Contracts\RoleContract;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Support\WildcardMatcher;

/**
 * Provides role functionality for models.
 *
 * @mixin Model
 */
trait HasRoles
{
    use HasPermissions {
        allPermissions as directPermissions;
    }

    /**
     * Get the roles relationship.
     */
    public function roles(): MorphToMany
    {
        /** @var class-string<RoleContract&Model> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);
        $subjectMorphKey = config('mandate.columns.pivot_subject_morph_key', 'subject');
        $contextEnabled = config('mandate.context.subject_roles', false);

        $relation = $this->morphToMany(
            $roleClass,
            $subjectMorphKey,
            config('mandate.tables.subject_roles', 'mandate_subject_roles'),
            "{$subjectMorphKey}_id",
            config('mandate.columns.pivot_role_key', 'role_id')
        );

        if ($contextEnabled) {
            $contextMorphName = config('mandate.columns.pivot_context_morph_name', 'context_model');
            $relation->withPivot(['scope', "{$contextMorphName}_type", "{$contextMorphName}_id"]);
        }

        return $relation->withTimestamps();
    }

    /**
     * Check if the model has been assigned a specific role.
     */
    public function assignedRole(
        string|RoleContract $role,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        $roleName = $this->resolveRoleName($role);

        return $this->getRolesQuery($scope, $contextModel)
            ->where('name', $roleName)
            ->exists();
    }

    /**
     * Check if the model has been assigned any of the given roles.
     *
     * @param  iterable<string|RoleContract>  $roles
     */
    public function assignedAnyRole(
        iterable $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        foreach ($roles as $role) {
            if ($this->assignedRole($role, $scope, $contextModel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has been assigned all of the given roles.
     *
     * @param  iterable<string|RoleContract>  $roles
     */
    public function assignedAllRoles(
        iterable $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        $hasRoles = false;

        foreach ($roles as $role) {
            $hasRoles = true;
            if (! $this->assignedRole($role, $scope, $contextModel)) {
                return false;
            }
        }

        // Return false for empty array (not vacuous truth)
        return $hasRoles;
    }

    /**
     * Assign roles to the model.
     *
     * @param  string|iterable<string>|RoleContract  $roles
     * @return $this
     */
    public function assignRole(
        string|iterable|RoleContract $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): static {
        $resolved = $this->resolveRoles($roles);
        $pivotData = $this->buildRoleContextPivotData($scope, $contextModel);

        $syncData = [];
        foreach ($resolved as $role) {
            $syncData[$role->getKey()] = $pivotData;
        }

        $this->roles()->syncWithoutDetaching($syncData);

        return $this;
    }

    /**
     * Remove roles from the model.
     *
     * @return $this
     */
    public function unassignRole(string|iterable|RoleContract $roles): static
    {
        $resolved = $this->resolveRoles($roles);

        $this->roles()->detach($resolved->pluck('id'));

        return $this;
    }

    /**
     * Sync roles for the model.
     *
     * @param  iterable<string|RoleContract>  $roles
     * @return $this
     */
    public function syncRoles(
        iterable $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): static {
        $resolved = $this->resolveRoles($roles);
        $pivotData = $this->buildRoleContextPivotData($scope, $contextModel);

        $syncData = [];
        foreach ($resolved as $role) {
            $syncData[$role->getKey()] = $pivotData;
        }

        $this->roles()->sync($syncData);

        return $this;
    }

    /**
     * Get all role names for the model.
     *
     * @return array<string>
     */
    public function roleNames(): array
    {
        return $this->allRoles()->pluck('name')->all();
    }

    /**
     * Get all roles for the model.
     *
     * @return Collection<int, RoleContract>
     */
    public function allRoles(): Collection
    {
        return $this->roles;
    }

    /**
     * Get permissions through assigned roles.
     *
     * @return Collection<int, PermissionContract>
     */
    public function permissionsThroughRoles(): Collection
    {
        $permissions = collect();

        foreach ($this->roles as $role) {
            // Get direct permissions and inherited permissions
            if (method_exists($role, 'allPermissions')) {
                $permissions = $permissions->merge($role->allPermissions());
            } else {
                $permissions = $permissions->merge($role->permissions);
            }
        }

        return $permissions->unique('id')->values();
    }

    /**
     * Get all permissions for the model (direct + through roles).
     *
     * @return Collection<int, PermissionContract>
     */
    public function allPermissions(): Collection
    {
        $directPermissions = $this->directPermissions();
        $rolePermissions = $this->permissionsThroughRoles();

        return $directPermissions->merge($rolePermissions)->unique('id')->values();
    }

    /**
     * Override granted to also check role permissions.
     *
     * This method is feature-aware - if a permission is gated by a feature,
     * it will return false if the feature is inactive for this user.
     */
    public function granted(
        string|PermissionContract $permission,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        $permissionName = $this->resolvePermissionName($permission);

        // Check direct permissions first (from HasPermissions trait)
        $hasDirectPermission = $this->grantedDirectPermission($permissionName, $scope, $contextModel);

        // Check permissions through roles
        $hasThroughRole = $this->assignedPermissionThroughRole($permissionName);

        // User must have the permission either directly or through roles
        if (! $hasDirectPermission && ! $hasThroughRole) {
            return false;
        }

        // Check if permission is feature-gated
        return $this->isPermissionFeatureActive($permissionName);
    }

    /**
     * Check if the model has a direct permission (not through roles).
     */
    protected function grantedDirectPermission(
        string $permissionName,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        // Check with wildcards if enabled
        if (config('mandate.wildcards', false)) {
            return $this->grantedPermissionWithWildcard($permissionName, $scope, $contextModel);
        }

        return $this->getPermissionsQuery($scope, $contextModel)
            ->where('name', $permissionName)
            ->exists();
    }

    /**
     * Check if the model has a permission through any of its roles.
     */
    protected function assignedPermissionThroughRole(string $permissionName): bool
    {
        $wildcardEnabled = config('mandate.wildcards', false);

        foreach ($this->roles as $role) {
            // Get all permissions for the role (including inherited)
            $rolePermissions = method_exists($role, 'allPermissions')
                ? $role->allPermissions()
                : $role->permissions;

            foreach ($rolePermissions as $permission) {
                $name = $permission->getAttribute('name');

                if ($name === $permissionName) {
                    return true;
                }

                // Check wildcard match
                if ($wildcardEnabled && WildcardMatcher::matches($name, $permissionName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the roles query with context filtering.
     *
     * @return Builder<RoleContract&Model>
     */
    protected function getRolesQuery(
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): Builder {
        $query = $this->roles()->getQuery();

        if (config('mandate.context.subject_roles', false)) {
            $pivotTable = config('mandate.tables.subject_roles', 'mandate_subject_roles');
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
     * Build pivot data for role context columns.
     *
     * @return array<string, mixed>
     */
    protected function buildRoleContextPivotData(
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): array {
        if (! config('mandate.context.subject_roles', false)) {
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
     * Resolve a role to its name.
     */
    protected function resolveRoleName(string|RoleContract $role): string
    {
        if ($role instanceof RoleContract) {
            return $role->getAttribute('name');
        }

        return $role;
    }

    /**
     * Resolve roles to a collection of Role models.
     *
     * @param  string|iterable<string|RoleContract>|RoleContract  $roles
     * @return Collection<int, RoleContract&Model>
     */
    protected function resolveRoles(string|iterable|RoleContract $roles): Collection
    {
        if ($roles instanceof RoleContract) {
            return collect([$roles]);
        }

        if (is_string($roles)) {
            $roles = [$roles];
        }

        /** @var class-string<RoleContract&Model> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        $resolved = collect();
        $guardName = $this->getGuardName();

        foreach ($roles as $role) {
            if ($role instanceof RoleContract) {
                $resolved->push($role);
            } else {
                $model = $roleClass::findByName($role, $guardName);
                if ($model !== null) {
                    $resolved->push($model);
                }
            }
        }

        return $resolved;
    }
}
