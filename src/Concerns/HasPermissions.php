<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\WildcardHandler;
use OffloadProject\Mandate\Events\PermissionGranted;
use OffloadProject\Mandate\Events\PermissionRevoked;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;
use OffloadProject\Mandate\Models\Permission;

/**
 * Trait for models that can have permissions.
 *
 * Add this trait to your subject model (User or any model) to enable permission management.
 *
 * @method MorphToMany morphToMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, ?string $relation = null, bool $inverse = false)
 */
trait HasPermissions
{
    use HasContext;

    /**
     * Boot the trait.
     */
    public static function bootHasPermissions(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->permissions()->detach();
        });
    }

    /**
     * Get all permissions directly assigned to this model.
     *
     * @return MorphToMany<Permission>
     */
    public function permissions(): MorphToMany
    {
        $relation = $this->morphToMany(
            config('mandate.models.permission', Permission::class),
            'subject',
            config('mandate.tables.permission_subject', 'permission_subject'),
            config('mandate.column_names.subject_morph_key', 'subject_id'),
            config('mandate.column_names.permission_id', 'permission_id')
        )->withTimestamps();

        // Include context columns in pivot if context is enabled
        if ($this->contextEnabled()) {
            $relation->withPivot([
                $this->getContextTypeColumn(),
                $this->getContextIdColumn(),
            ]);
        }

        return $relation;
    }

    /**
     * Grant one or more permissions to this model.
     *
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function grantPermission(string|BackedEnum|PermissionContract|array $permissions, ?Model $context = null): static
    {
        $permissionNames = $this->collectPermissionNames($permissions);
        $normalizedIds = $this->normalizePermissions($permissions);

        $this->attachWithContext($this->permissions(), $normalizedIds, $context);

        $this->forgetPermissionCache();

        if (config('mandate.events', false)) {
            PermissionGranted::dispatch($this, $permissionNames);
        }

        return $this;
    }

    /**
     * Alias for grantPermission() - grant multiple permissions.
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function grantPermissions(array $permissions, ?Model $context = null): static
    {
        return $this->grantPermission($permissions, $context);
    }

    /**
     * Revoke one or more permissions from this model.
     *
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function revokePermission(string|BackedEnum|PermissionContract|array $permissions, ?Model $context = null): static
    {
        $permissionNames = $this->collectPermissionNames($permissions);
        $normalizedIds = $this->normalizePermissions($permissions);

        $this->detachWithContext($this->permissions(), $normalizedIds, $context);

        $this->forgetPermissionCache();

        if (config('mandate.events', false)) {
            PermissionRevoked::dispatch($this, $permissionNames);
        }

        return $this;
    }

    /**
     * Alias for revokePermission() - revoke multiple permissions.
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function revokePermissions(array $permissions, ?Model $context = null): static
    {
        return $this->revokePermission($permissions, $context);
    }

    /**
     * Sync permissions on this model (replace all existing).
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function syncPermissions(array $permissions, ?Model $context = null): static
    {
        $normalized = $this->normalizePermissions($permissions);

        $this->syncWithContext($this->permissions(), $normalized, $context);

        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Check if the model has a specific permission (direct or via role).
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasPermission(string|BackedEnum|PermissionContract $permission, ?Model $context = null): bool
    {
        $permissionName = $this->getPermissionName($permission);

        // Check wildcard permissions if enabled
        if (config('mandate.wildcards.enabled', false)) {
            if ($this->hasWildcardPermission($permissionName, $context)) {
                return true;
            }
        }

        // Check direct permissions
        if ($this->hasDirectPermission($permissionName, $context)) {
            return true;
        }

        // Check permissions via roles (if model uses HasRoles)
        if (method_exists($this, 'hasPermissionViaRole')) {
            return $this->hasPermissionViaRole($permissionName, $context);
        }

        return false;
    }

    /**
     * Check if the model has any of the given permissions.
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasAnyPermission(array $permissions, ?Model $context = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has all of the given permissions.
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasAllPermissions(array $permissions, ?Model $context = null): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the model has a permission directly (not via role).
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasDirectPermission(string|BackedEnum|PermissionContract $permission, ?Model $context = null): bool
    {
        $permissionName = $this->getPermissionName($permission);
        $guardName = $this->getGuardName();

        // Build the query with context support
        $query = $this->permissions()
            ->where('name', $permissionName)
            ->where('guard', $guardName);

        if ($this->contextEnabled()) {
            $resolved = $this->resolveContext($context);

            // Check for specific context
            if ($context !== null) {
                if ($this->globalFallbackEnabled()) {
                    // Check for context OR global
                    $query->where(function ($q) use ($resolved) {
                        $table = config('mandate.tables.permission_subject', 'permission_subject');
                        $typeCol = $this->getContextTypeColumn();
                        $idCol = $this->getContextIdColumn();

                        $q->where(function ($inner) use ($table, $typeCol, $idCol, $resolved) {
                            $inner->where("{$table}.{$typeCol}", $resolved['type'])
                                ->where("{$table}.{$idCol}", $resolved['id']);
                        })->orWhere(function ($inner) use ($table, $typeCol, $idCol) {
                            $inner->whereNull("{$table}.{$typeCol}")
                                ->whereNull("{$table}.{$idCol}");
                        });
                    });
                } else {
                    // Check for specific context only
                    $query->wherePivot($this->getContextTypeColumn(), $resolved['type'])
                        ->wherePivot($this->getContextIdColumn(), $resolved['id']);
                }
            } else {
                // Check for global (null context) only
                $query->wherePivot($this->getContextTypeColumn(), null)
                    ->wherePivot($this->getContextIdColumn(), null);
            }
        }

        return $query->exists();
    }

    /**
     * Get all permission names for this model.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, string>
     */
    public function getPermissionNames(?Model $context = null): Collection
    {
        return $this->getAllPermissions($context)->pluck('name');
    }

    /**
     * Get all permissions for this model (direct + via roles + via capabilities).
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, PermissionContract>
     */
    public function getAllPermissions(?Model $context = null): Collection
    {
        // Get direct permissions
        $permissions = $this->getDirectPermissions($context)->keyBy('id');

        // Add permissions from roles if applicable
        if (method_exists($this, 'getPermissionsViaRoles')) {
            $rolePermissions = $this->getPermissionsViaRoles($context);
            $permissions = $permissions->merge($rolePermissions->keyBy('id'));
        }

        // Add permissions from capabilities if applicable
        if (method_exists($this, 'getPermissionsViaCapabilities')) {
            $capabilityPermissions = $this->getPermissionsViaCapabilities($context);
            $permissions = $permissions->merge($capabilityPermissions->keyBy('id'));
        }

        return $permissions->values();
    }

    /**
     * Get only directly assigned permissions.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, PermissionContract>
     */
    public function getDirectPermissions(?Model $context = null): Collection
    {
        if (! $this->contextEnabled()) {
            return $this->permissions;
        }

        $query = $this->permissions();
        $resolved = $this->resolveContext($context);

        if ($context !== null && $this->globalFallbackEnabled()) {
            // Get permissions for context OR global
            $table = config('mandate.tables.permission_subject', 'permission_subject');
            $typeCol = $this->getContextTypeColumn();
            $idCol = $this->getContextIdColumn();

            $query->where(function ($q) use ($table, $typeCol, $idCol, $resolved) {
                $q->where(function ($inner) use ($table, $typeCol, $idCol, $resolved) {
                    $inner->where("{$table}.{$typeCol}", $resolved['type'])
                        ->where("{$table}.{$idCol}", $resolved['id']);
                })->orWhere(function ($inner) use ($table, $typeCol, $idCol) {
                    $inner->whereNull("{$table}.{$typeCol}")
                        ->whereNull("{$table}.{$idCol}");
                });
            });
        } else {
            // Get permissions for specific context only
            $query->wherePivot($this->getContextTypeColumn(), $resolved['type'])
                ->wherePivot($this->getContextIdColumn(), $resolved['id']);
        }

        return $query->get();
    }

    /**
     * Get all contexts where this model has a specific permission.
     *
     * @return Collection<int, Model>
     */
    public function getPermissionContexts(string|BackedEnum|PermissionContract $permission): Collection
    {
        if (! $this->contextEnabled()) {
            return collect();
        }

        $permissionName = $this->getPermissionName($permission);
        $guardName = $this->getGuardName();

        $pivotRecords = $this->permissions()
            ->where('name', $permissionName)
            ->where('guard', $guardName)
            ->whereNotNull($this->getContextTypeColumn())
            ->get();

        return $pivotRecords->map(function ($permission) {
            $contextType = $permission->pivot->{$this->getContextTypeColumn()};
            $contextId = $permission->pivot->{$this->getContextIdColumn()};

            if ($contextType && $contextId) {
                return $contextType::find($contextId);
            }

            return null;
        })->filter()->values();
    }

    /**
     * Scope query to models with a specific permission.
     *
     * @param  Builder<static>  $query
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @return Builder<static>
     */
    public function scopePermission(Builder $query, string|BackedEnum|PermissionContract|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $permissionNames = array_map(fn ($p) => $this->getPermissionName($p), $permissions);

        return $query->whereHas('permissions', function (Builder $q) use ($permissionNames) {
            $q->whereIn('name', $permissionNames);
        });
    }

    /**
     * Scope query to models without a specific permission.
     *
     * @param  Builder<static>  $query
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @return Builder<static>
     */
    public function scopeWithoutPermission(Builder $query, string|BackedEnum|PermissionContract|array $permissions): Builder
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $permissionNames = array_map(fn ($p) => $this->getPermissionName($p), $permissions);

        return $query->whereDoesntHave('permissions', function (Builder $q) use ($permissionNames) {
            $q->whereIn('name', $permissionNames);
        });
    }

    /**
     * Get the guard name for this model.
     */
    public function getGuardName(): string
    {
        return Guard::getNameForModel($this);
    }

    /**
     * Check if model has permission via wildcard patterns.
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    protected function hasWildcardPermission(string $permission, ?Model $context = null): bool
    {
        $wildcardHandler = $this->getWildcardHandler();

        foreach ($this->getAllPermissions($context) as $grantedPermission) {
            if ($wildcardHandler->containsWildcard($grantedPermission->name)) {
                if ($wildcardHandler->matches($grantedPermission->name, $permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize permissions to an array of IDs.
     *
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @return array<int|string>
     */
    protected function normalizePermissions(string|BackedEnum|PermissionContract|array $permissions): array
    {
        if (! is_array($permissions)) {
            $permissions = [$permissions];
        }

        $normalized = [];
        $guard = $this->getGuardName();

        foreach ($permissions as $permission) {
            if ($permission instanceof PermissionContract) {
                Guard::assertMatch($guard, $permission->guard, 'permission');
                $normalized[] = $permission->getKey();
            } else {
                $permissionName = $this->getPermissionName($permission);
                $permissionModel = $this->getPermissionClass()::findByName($permissionName, $guard);
                $normalized[] = $permissionModel->getKey();
            }
        }

        return $normalized;
    }

    /**
     * Get the permission name from various input types.
     */
    protected function getPermissionName(string|BackedEnum|PermissionContract $permission): string
    {
        if ($permission instanceof BackedEnum) {
            return (string) $permission->value;
        }

        if ($permission instanceof PermissionContract) {
            return $permission->name;
        }

        return $permission;
    }

    /**
     * Collect permission names from various input types.
     *
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @return array<string>
     */
    protected function collectPermissionNames(string|BackedEnum|PermissionContract|array $permissions): array
    {
        if (! is_array($permissions)) {
            $permissions = [$permissions];
        }

        return array_map(fn ($p) => $this->getPermissionName($p), $permissions);
    }

    /**
     * Get the permission model class.
     *
     * @return class-string<Permission>
     */
    protected function getPermissionClass(): string
    {
        return config('mandate.models.permission', Permission::class);
    }

    /**
     * Get the wildcard handler instance.
     */
    protected function getWildcardHandler(): WildcardHandler
    {
        $handlerClass = config('mandate.wildcards.handler');

        return app($handlerClass);
    }

    /**
     * Clear the cached permissions.
     */
    protected function forgetPermissionCache(): void
    {
        app(MandateRegistrar::class)->forgetCachedPermissions();
    }
}
