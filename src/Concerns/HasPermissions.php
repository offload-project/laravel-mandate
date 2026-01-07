<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->morphToMany(
            config('mandate.models.permission', Permission::class),
            'subject',
            config('mandate.tables.permission_subject', 'permission_subject'),
            config('mandate.column_names.subject_morph_key', 'subject_id'),
            config('mandate.column_names.permission_id', 'permission_id')
        )->withTimestamps();
    }

    /**
     * Grant one or more permissions to this model.
     *
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @return $this
     */
    public function grantPermission(string|BackedEnum|PermissionContract|array $permissions): static
    {
        $permissionNames = $this->collectPermissionNames($permissions);
        $normalizedIds = $this->normalizePermissions($permissions);

        $this->permissions()->syncWithoutDetaching($normalizedIds);

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
     * @return $this
     */
    public function grantPermissions(array $permissions): static
    {
        return $this->grantPermission($permissions);
    }

    /**
     * Revoke one or more permissions from this model.
     *
     * @param  string|BackedEnum|PermissionContract|array<string|BackedEnum|PermissionContract>  $permissions
     * @return $this
     */
    public function revokePermission(string|BackedEnum|PermissionContract|array $permissions): static
    {
        $permissionNames = $this->collectPermissionNames($permissions);
        $normalizedIds = $this->normalizePermissions($permissions);

        $this->permissions()->detach($normalizedIds);

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
     * @return $this
     */
    public function revokePermissions(array $permissions): static
    {
        return $this->revokePermission($permissions);
    }

    /**
     * Sync permissions on this model (replace all existing).
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     * @return $this
     */
    public function syncPermissions(array $permissions): static
    {
        $normalized = $this->normalizePermissions($permissions);

        $this->permissions()->sync($normalized);

        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Check if the model has a specific permission (direct or via role).
     */
    public function hasPermission(string|BackedEnum|PermissionContract $permission): bool
    {
        $permissionName = $this->getPermissionName($permission);

        // Check wildcard permissions if enabled
        if (config('mandate.wildcards.enabled', false)) {
            if ($this->hasWildcardPermission($permissionName)) {
                return true;
            }
        }

        // Check direct permissions
        if ($this->hasDirectPermission($permissionName)) {
            return true;
        }

        // Check permissions via roles (if model uses HasRoles)
        if (method_exists($this, 'hasPermissionViaRole')) {
            return $this->hasPermissionViaRole($permissionName);
        }

        return false;
    }

    /**
     * Check if the model has any of the given permissions.
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has all of the given permissions.
     *
     * @param  array<string|BackedEnum|PermissionContract>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the model has a permission directly (not via role).
     */
    public function hasDirectPermission(string|BackedEnum|PermissionContract $permission): bool
    {
        $permissionName = $this->getPermissionName($permission);
        $guardName = $this->getGuardName();

        // If permissions are already loaded, check in-memory (avoids N+1)
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains(
                fn ($p) => $p->name === $permissionName && $p->guard === $guardName
            );
        }

        return $this->permissions()
            ->where('name', $permissionName)
            ->where('guard', $guardName)
            ->exists();
    }

    /**
     * Get all permission names for this model.
     *
     * @return Collection<int, string>
     */
    public function getPermissionNames(): Collection
    {
        return $this->getAllPermissions()->pluck('name');
    }

    /**
     * Get all permissions for this model (direct + via roles + via capabilities).
     *
     * @return Collection<int, PermissionContract>
     */
    public function getAllPermissions(): Collection
    {
        // Get direct permissions
        $permissions = $this->permissions->keyBy('id');

        // Add permissions from roles if applicable
        if (method_exists($this, 'getPermissionsViaRoles')) {
            $rolePermissions = $this->getPermissionsViaRoles();
            $permissions = $permissions->merge($rolePermissions->keyBy('id'));
        }

        // Add permissions from capabilities if applicable
        if (method_exists($this, 'getPermissionsViaCapabilities')) {
            $capabilityPermissions = $this->getPermissionsViaCapabilities();
            $permissions = $permissions->merge($capabilityPermissions->keyBy('id'));
        }

        return $permissions->values();
    }

    /**
     * Get only directly assigned permissions.
     *
     * @return Collection<int, PermissionContract>
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
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
     */
    protected function hasWildcardPermission(string $permission): bool
    {
        $wildcardHandler = $this->getWildcardHandler();

        foreach ($this->getAllPermissions() as $grantedPermission) {
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
