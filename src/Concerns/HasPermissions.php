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
    use ChecksFeatureAccess;
    use HasContext;
    use LogsAuthorization;

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
            $this->getSubjectMorphName(),
            config('mandate.tables.permission_subject', 'permission_subject'),
            $this->getSubjectIdColumn(),
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

        $this->logPermissionGranted($permissionNames, $context);

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

        $this->logPermissionRevoked($permissionNames, $context);

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
     * @param  bool  $bypassFeatureCheck  Skip feature access check (e.g., admin override)
     */
    public function hasPermission(string|BackedEnum|PermissionContract $permission, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        // Check feature access first if context is a Feature
        if (! $this->checkFeatureAccess($context, $bypassFeatureCheck)) {
            return false;
        }

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
     * @param  bool  $bypassFeatureCheck  Skip feature access check (e.g., admin override)
     */
    public function hasAnyPermission(array $permissions, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $context, $bypassFeatureCheck)) {
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
     * @param  bool  $bypassFeatureCheck  Skip feature access check (e.g., admin override)
     */
    public function hasAllPermissions(array $permissions, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission, $context, $bypassFeatureCheck)) {
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

        $pivotTable = config('mandate.tables.permission_subject', 'permission_subject');
        $query = $this->applyContextConstraints($query, $context, $pivotTable);

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
        $pivotTable = config('mandate.tables.permission_subject', 'permission_subject');
        $query = $this->applyContextConstraints($query, $context, $pivotTable);

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

        $pivotTable = config('mandate.tables.permission_subject', 'permission_subject');
        $pivotRecords = $this->permissions()
            ->where('name', $permissionName)
            ->where('guard', $guardName)
            ->whereNotNull("{$pivotTable}.{$this->getContextTypeColumn()}")
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
     * Optimized to filter wildcard permissions first before matching.
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    protected function hasWildcardPermission(string $permission, ?Model $context = null): bool
    {
        $wildcardHandler = $this->getWildcardHandler();

        // Filter to only permissions containing wildcards first
        $wildcardPermissions = $this->getAllPermissions($context)
            ->filter(fn ($p) => $wildcardHandler->containsWildcard($p->name));

        // Early exit if no wildcard permissions
        if ($wildcardPermissions->isEmpty()) {
            return false;
        }

        foreach ($wildcardPermissions as $grantedPermission) {
            if ($wildcardHandler->matches($grantedPermission->name, $permission)) {
                return true;
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
