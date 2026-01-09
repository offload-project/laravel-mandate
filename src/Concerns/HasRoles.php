<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Events\CapabilityAssigned;
use OffloadProject\Mandate\Events\CapabilityRemoved;
use OffloadProject\Mandate\Events\RoleAssigned;
use OffloadProject\Mandate\Events\RoleRemoved;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\MandateRegistrar;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Role;

/**
 * Trait for models that can have roles.
 *
 * Add this trait to your subject model (User or any model) to enable role management.
 * This trait should be used alongside HasPermissions for full functionality.
 *
 * @method MorphToMany morphToMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, ?string $relation = null, bool $inverse = false)
 */
trait HasRoles
{
    use HasPermissions;

    /**
     * Boot the trait.
     */
    public static function bootHasRoles(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
        });
    }

    /**
     * Get all roles assigned to this model.
     *
     * @return MorphToMany<Role>
     */
    public function roles(): MorphToMany
    {
        $relation = $this->morphToMany(
            config('mandate.models.role', Role::class),
            $this->getSubjectMorphName(),
            config('mandate.tables.role_subject', 'role_subject'),
            $this->getSubjectIdColumn(),
            config('mandate.column_names.role_id', 'role_id')
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
     * Assign one or more roles to this model.
     *
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role assignment
     * @return $this
     */
    public function assignRole(string|BackedEnum|RoleContract|array $roles, ?Model $context = null): static
    {
        $roleNames = $this->collectRoleNames($roles);
        $normalizedIds = $this->normalizeRoles($roles);

        $this->attachWithContext($this->roles(), $normalizedIds, $context);

        $this->forgetPermissionCache();

        $this->logRoleAssigned($roleNames, $context);

        if (config('mandate.events', false)) {
            RoleAssigned::dispatch($this, $roleNames);
        }

        return $this;
    }

    /**
     * Alias for assignRole() - assign multiple roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role assignment
     * @return $this
     */
    public function assignRoles(array $roles, ?Model $context = null): static
    {
        return $this->assignRole($roles, $context);
    }

    /**
     * Remove one or more roles from this model.
     *
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role removal
     * @return $this
     */
    public function removeRole(string|BackedEnum|RoleContract|array $roles, ?Model $context = null): static
    {
        $roleNames = $this->collectRoleNames($roles);
        $normalizedIds = $this->normalizeRoles($roles);

        $this->detachWithContext($this->roles(), $normalizedIds, $context);

        $this->forgetPermissionCache();

        $this->logRoleRemoved($roleNames, $context);

        if (config('mandate.events', false)) {
            RoleRemoved::dispatch($this, $roleNames);
        }

        return $this;
    }

    /**
     * Alias for removeRole() - remove multiple roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role removal
     * @return $this
     */
    public function removeRoles(array $roles, ?Model $context = null): static
    {
        return $this->removeRole($roles, $context);
    }

    /**
     * Sync roles on this model (replace all existing).
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role sync
     * @return $this
     */
    public function syncRoles(array $roles, ?Model $context = null): static
    {
        $normalized = $this->normalizeRoles($roles);

        $this->syncWithContext($this->roles(), $normalized, $context);

        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Check if the model has a specific role.
     *
     * @param  Model|null  $context  Optional context model for scoped role check
     * @param  bool  $bypassFeatureCheck  Skip feature access check (for admin override scenarios)
     */
    public function hasRole(string|BackedEnum|RoleContract $role, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        // Check feature access first if context is a Feature
        if (! $this->checkFeatureAccess($context, $bypassFeatureCheck)) {
            return false;
        }

        $roleName = $this->getRoleName($role);
        $guardName = $this->getGuardName();

        $query = $this->roles()
            ->where('name', $roleName)
            ->where('guard', $guardName);

        $pivotTable = config('mandate.tables.role_subject', 'role_subject');
        $query = $this->applyContextConstraints($query, $context, $pivotTable);

        return $query->exists();
    }

    /**
     * Check if the model has any of the given roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     * @param  bool  $bypassFeatureCheck  Skip feature access check (for admin override scenarios)
     */
    public function hasAnyRole(array $roles, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $context, $bypassFeatureCheck)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has all of the given roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     * @param  bool  $bypassFeatureCheck  Skip feature access check (for admin override scenarios)
     */
    public function hasAllRoles(array $roles, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role, $context, $bypassFeatureCheck)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the model has exactly the given roles (no more, no less).
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     * @param  bool  $bypassFeatureCheck  Skip feature access check (for admin override scenarios)
     */
    public function hasExactRoles(array $roles, ?Model $context = null, bool $bypassFeatureCheck = false): bool
    {
        // Check feature access first if context is a Feature
        if (! $this->checkFeatureAccess($context, $bypassFeatureCheck)) {
            return false;
        }

        $guard = $this->getGuardName();
        $query = $this->roles()->where('guard', $guard);

        if ($this->contextEnabled()) {
            $resolved = $this->resolveContext($context);
            $query->wherePivot($this->getContextTypeColumn(), $resolved['type'])
                ->wherePivot($this->getContextIdColumn(), $resolved['id']);
        }

        $assignedRoles = $query->pluck('name')->sort()->values();

        $expectedRoles = collect($roles)
            ->map(fn ($role) => $this->getRoleName($role))
            ->sort()
            ->values();

        return $assignedRoles->count() === $expectedRoles->count()
            && $assignedRoles->diff($expectedRoles)->isEmpty();
    }

    /**
     * Get all role names for this model.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, string>
     */
    public function getRoleNames(?Model $context = null): Collection
    {
        return $this->getRolesForContext($context)->pluck('name');
    }

    /**
     * Get roles for a specific context.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, RoleContract>
     */
    public function getRolesForContext(?Model $context = null): Collection
    {
        if (! $this->contextEnabled()) {
            return $this->roles;
        }

        $query = $this->roles();
        $pivotTable = config('mandate.tables.role_subject', 'role_subject');
        $query = $this->applyContextConstraints($query, $context, $pivotTable);

        return $query->get();
    }

    /**
     * Get all contexts where this model has a specific role.
     *
     * @return Collection<int, Model>
     */
    public function getRoleContexts(string|BackedEnum|RoleContract $role): Collection
    {
        if (! $this->contextEnabled()) {
            return collect();
        }

        $roleName = $this->getRoleName($role);
        $guardName = $this->getGuardName();

        $pivotTable = config('mandate.tables.role_subject', 'role_subject');
        $pivotRecords = $this->roles()
            ->where('name', $roleName)
            ->where('guard', $guardName)
            ->whereNotNull("{$pivotTable}.{$this->getContextTypeColumn()}")
            ->get();

        return $pivotRecords->map(function ($role) {
            $contextType = $role->pivot->{$this->getContextTypeColumn()};
            $contextId = $role->pivot->{$this->getContextIdColumn()};

            if ($contextType && $contextId) {
                return $contextType::find($contextId);
            }

            return null;
        })->filter()->values();
    }

    /**
     * Check if the model has a permission via one of its roles (including capabilities).
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasPermissionViaRole(string $permission, ?Model $context = null): bool
    {
        $guardName = $this->getGuardName();

        // Build query for roles with context support
        $rolesQuery = $this->roles();
        $pivotTable = config('mandate.tables.role_subject', 'role_subject');
        $rolesQuery = $this->applyContextConstraints($rolesQuery, $context, $pivotTable);

        // Check direct role permissions
        $hasDirectRolePermission = (clone $rolesQuery)
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission)->where('guard', $guardName))
            ->exists();

        if ($hasDirectRolePermission) {
            return true;
        }

        // Check permissions via capabilities
        return $this->hasPermissionViaCapability($permission, $context);
    }

    /**
     * Get all permissions granted via roles.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, PermissionContract>
     */
    public function getPermissionsViaRoles(?Model $context = null): Collection
    {
        // Get roles for context
        $roles = $this->getRolesForContext($context);

        // Ensure permissions are loaded
        if (! $roles->every(fn ($r) => $r->relationLoaded('permissions'))) {
            $roleClass = config('mandate.models.role', Role::class);
            $roles = $roleClass::query()
                ->whereIn('id', $roles->pluck('id'))
                ->with('permissions')
                ->get();
        }

        return $roles->flatMap->permissions->unique('id')->values();
    }

    /**
     * Get all capabilities assigned directly to this model.
     *
     * Only available when direct_assignment is enabled in config.
     *
     * @return MorphToMany<Capability>
     */
    public function capabilities(): MorphToMany
    {
        return $this->morphToMany(
            config('mandate.models.capability', Capability::class),
            $this->getSubjectMorphName(),
            config('mandate.tables.capability_subject', 'capability_subject'),
            $this->getSubjectIdColumn(),
            config('mandate.column_names.capability_id', 'capability_id')
        )->withTimestamps();
    }

    /**
     * Assign one or more capabilities directly to this model.
     *
     * Only works when direct_assignment is enabled in config.
     *
     * @param  string|BackedEnum|CapabilityContract|array<string|BackedEnum|CapabilityContract>  $capabilities
     * @return $this
     */
    public function assignCapability(string|BackedEnum|CapabilityContract|array $capabilities): static
    {
        if (! config('mandate.capabilities.enabled', false) || ! config('mandate.capabilities.direct_assignment', false)) {
            return $this;
        }

        $capabilityNames = $this->collectCapabilityNames($capabilities);
        $normalizedIds = $this->normalizeCapabilities($capabilities);

        $this->capabilities()->syncWithoutDetaching($normalizedIds);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        if (config('mandate.events', false)) {
            CapabilityAssigned::dispatch($this, $capabilityNames);
        }

        return $this;
    }

    /**
     * Remove one or more capabilities from this model.
     *
     * Only works when direct_assignment is enabled in config.
     *
     * @param  string|BackedEnum|CapabilityContract|array<string|BackedEnum|CapabilityContract>  $capabilities
     * @return $this
     */
    public function removeCapability(string|BackedEnum|CapabilityContract|array $capabilities): static
    {
        if (! config('mandate.capabilities.enabled', false) || ! config('mandate.capabilities.direct_assignment', false)) {
            return $this;
        }

        $capabilityNames = $this->collectCapabilityNames($capabilities);
        $normalizedIds = $this->normalizeCapabilities($capabilities);

        $this->capabilities()->detach($normalizedIds);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        if (config('mandate.events', false)) {
            CapabilityRemoved::dispatch($this, $capabilityNames);
        }

        return $this;
    }

    /**
     * Sync capabilities on this model (replace all existing).
     *
     * Only works when direct_assignment is enabled in config.
     *
     * @param  array<string|BackedEnum|CapabilityContract>  $capabilities
     * @return $this
     */
    public function syncCapabilities(array $capabilities): static
    {
        if (! config('mandate.capabilities.enabled', false) || ! config('mandate.capabilities.direct_assignment', false)) {
            $this->capabilities()->sync([]);

            return $this;
        }

        $normalized = $this->normalizeCapabilities($capabilities);

        $this->capabilities()->sync($normalized);

        app(MandateRegistrar::class)->forgetCachedPermissions();

        return $this;
    }

    /**
     * Check if the model has a specific capability (via roles or directly).
     */
    public function hasCapability(string|BackedEnum|CapabilityContract $capability): bool
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return false;
        }

        $capabilityName = $this->getCapabilityName($capability);

        // Check direct capabilities first (if enabled)
        if (config('mandate.capabilities.direct_assignment', false)) {
            if ($this->hasDirectCapability($capabilityName)) {
                return true;
            }
        }

        // Check capabilities via roles
        return $this->hasCapabilityViaRole($capabilityName);
    }

    /**
     * Check if the model has any of the given capabilities.
     *
     * @param  array<string|BackedEnum|CapabilityContract>  $capabilities
     */
    public function hasAnyCapability(array $capabilities): bool
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return false;
        }

        foreach ($capabilities as $capability) {
            if ($this->hasCapability($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the model has all of the given capabilities.
     *
     * @param  array<string|BackedEnum|CapabilityContract>  $capabilities
     */
    public function hasAllCapabilities(array $capabilities): bool
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return false;
        }

        foreach ($capabilities as $capability) {
            if (! $this->hasCapability($capability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the model has a capability directly assigned.
     */
    public function hasDirectCapability(string $capability): bool
    {
        if (! config('mandate.capabilities.direct_assignment', false)) {
            return false;
        }

        $guardName = $this->getGuardName();

        if ($this->relationLoaded('capabilities')) {
            return $this->capabilities->contains(
                fn ($c) => $c->name === $capability && $c->guard === $guardName
            );
        }

        return $this->capabilities()
            ->where('name', $capability)
            ->where('guard', $guardName)
            ->exists();
    }

    /**
     * Check if the model has a capability via one of its roles.
     */
    public function hasCapabilityViaRole(string $capability): bool
    {
        $guardName = $this->getGuardName();

        if ($this->relationLoaded('roles')) {
            foreach ($this->roles as $role) {
                if ($role->relationLoaded('capabilities')) {
                    if ($role->capabilities->contains(
                        fn ($c) => $c->name === $capability && $c->guard === $guardName
                    )) {
                        return true;
                    }
                } else {
                    return $this->roles()
                        ->whereHas('capabilities', fn ($q) => $q->where('name', $capability)->where('guard', $guardName))
                        ->exists();
                }
            }

            return false;
        }

        return $this->roles()
            ->whereHas('capabilities', fn ($q) => $q->where('name', $capability)->where('guard', $guardName))
            ->exists();
    }

    /**
     * Get all capabilities for this model (direct + via roles).
     *
     * @param  Model|null  $context  Optional context model to filter capabilities
     * @return Collection<int, CapabilityContract>
     */
    public function getAllCapabilities(?Model $context = null): Collection
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return collect();
        }

        // Get direct capabilities if enabled
        $capabilities = config('mandate.capabilities.direct_assignment', false)
            ? $this->capabilities->keyBy('id')
            : collect();

        // Add capabilities from roles (with context)
        $roleCapabilities = $this->getCapabilitiesViaRoles($context);
        $capabilities = $capabilities->merge($roleCapabilities->keyBy('id'));

        return $capabilities->values();
    }

    /**
     * Get capabilities granted via roles.
     *
     * @param  Model|null  $context  Optional context model to filter capabilities
     * @return Collection<int, CapabilityContract>
     */
    public function getCapabilitiesViaRoles(?Model $context = null): Collection
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return collect();
        }

        // Get roles for context
        $roles = $this->getRolesForContext($context);

        // Ensure capabilities are loaded
        if (! $roles->every(fn ($r) => $r->relationLoaded('capabilities'))) {
            $roleClass = config('mandate.models.role', Role::class);
            $roles = $roleClass::query()
                ->whereIn('id', $roles->pluck('id'))
                ->with('capabilities')
                ->get();
        }

        return $roles->flatMap->capabilities->unique('id')->values();
    }

    /**
     * Get permissions granted via capabilities (from roles and direct).
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, PermissionContract>
     */
    public function getPermissionsViaCapabilities(?Model $context = null): Collection
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return collect();
        }

        $capabilities = $this->getAllCapabilities($context);

        // Ensure permissions are loaded
        if (! $capabilities->every(fn ($c) => $c->relationLoaded('permissions'))) {
            $capabilityClass = config('mandate.models.capability', Capability::class);
            $capabilities = $capabilityClass::query()
                ->whereIn('id', $capabilities->pluck('id'))
                ->with('permissions')
                ->get();
        }

        return $capabilities->flatMap->permissions->unique('id')->values();
    }

    /**
     * Check if model has permission via capability.
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasPermissionViaCapability(string $permission, ?Model $context = null): bool
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return false;
        }

        $guardName = $this->getGuardName();

        // Check direct capabilities first (if enabled)
        if (config('mandate.capabilities.direct_assignment', false)) {
            if ($this->capabilities()
                ->whereHas('permissions', fn ($q) => $q->where('name', $permission)->where('guard', $guardName))
                ->exists()) {
                return true;
            }
        }

        // Check capabilities via roles (with context)
        $rolesQuery = $this->roles();
        $pivotTable = config('mandate.tables.role_subject', 'role_subject');
        $rolesQuery = $this->applyContextConstraints($rolesQuery, $context, $pivotTable);

        return $rolesQuery
            ->whereHas('capabilities.permissions', fn ($q) => $q->where('name', $permission)->where('guard', $guardName))
            ->exists();
    }

    /**
     * Scope query to models with a specific role.
     *
     * @param  Builder<static>  $query
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @return Builder<static>
     */
    public function scopeRole(Builder $query, string|BackedEnum|RoleContract|array $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $roleNames = array_map(fn ($r) => $this->getRoleName($r), $roles);

        return $query->whereHas('roles', function (Builder $q) use ($roleNames) {
            $q->whereIn('name', $roleNames);
        });
    }

    /**
     * Scope query to models without a specific role.
     *
     * @param  Builder<static>  $query
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @return Builder<static>
     */
    public function scopeWithoutRole(Builder $query, string|BackedEnum|RoleContract|array $roles): Builder
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $roleNames = array_map(fn ($r) => $this->getRoleName($r), $roles);

        return $query->whereDoesntHave('roles', function (Builder $q) use ($roleNames) {
            $q->whereIn('name', $roleNames);
        });
    }

    /**
     * Get the capability name from various input types.
     */
    protected function getCapabilityName(string|BackedEnum|CapabilityContract $capability): string
    {
        if ($capability instanceof BackedEnum) {
            return (string) $capability->value;
        }

        if ($capability instanceof CapabilityContract) {
            return $capability->name;
        }

        return $capability;
    }

    /**
     * Get the capability model class.
     *
     * @return class-string<Capability>
     */
    protected function getCapabilityClass(): string
    {
        return config('mandate.models.capability', Capability::class);
    }

    /**
     * Collect capability names from various input types.
     *
     * @param  string|BackedEnum|CapabilityContract|array<string|BackedEnum|CapabilityContract>  $capabilities
     * @return array<string>
     */
    protected function collectCapabilityNames(string|BackedEnum|CapabilityContract|array $capabilities): array
    {
        if (! is_array($capabilities)) {
            $capabilities = [$capabilities];
        }

        return array_map(fn ($c) => $this->getCapabilityName($c), $capabilities);
    }

    /**
     * Normalize capabilities to an array of IDs.
     *
     * @param  string|BackedEnum|CapabilityContract|array<string|BackedEnum|CapabilityContract>  $capabilities
     * @return array<int|string>
     */
    protected function normalizeCapabilities(string|BackedEnum|CapabilityContract|array $capabilities): array
    {
        if (! is_array($capabilities)) {
            $capabilities = [$capabilities];
        }

        $normalized = [];
        $guard = $this->getGuardName();

        foreach ($capabilities as $capability) {
            if ($capability instanceof CapabilityContract) {
                Guard::assertMatch($guard, $capability->guard, 'capability');
                $normalized[] = $capability->getKey();
            } else {
                $capabilityName = $this->getCapabilityName($capability);
                $capabilityModel = $this->getCapabilityClass()::findByName($capabilityName, $guard);
                $normalized[] = $capabilityModel->getKey();
            }
        }

        return $normalized;
    }

    /**
     * Normalize roles to an array of IDs.
     *
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @return array<int|string>
     */
    protected function normalizeRoles(string|BackedEnum|RoleContract|array $roles): array
    {
        if (! is_array($roles)) {
            $roles = [$roles];
        }

        $normalized = [];
        $guard = $this->getGuardName();

        foreach ($roles as $role) {
            if ($role instanceof RoleContract) {
                Guard::assertMatch($guard, $role->guard, 'role');
                $normalized[] = $role->getKey();
            } else {
                $roleName = $this->getRoleName($role);
                $roleModel = $this->getRoleClass()::findByName($roleName, $guard);
                $normalized[] = $roleModel->getKey();
            }
        }

        return $normalized;
    }

    /**
     * Get the role name from various input types.
     */
    protected function getRoleName(string|BackedEnum|RoleContract $role): string
    {
        if ($role instanceof BackedEnum) {
            return (string) $role->value;
        }

        if ($role instanceof RoleContract) {
            return $role->name;
        }

        return $role;
    }

    /**
     * Collect role names from various input types.
     *
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @return array<string>
     */
    protected function collectRoleNames(string|BackedEnum|RoleContract|array $roles): array
    {
        if (! is_array($roles)) {
            $roles = [$roles];
        }

        return array_map(fn ($r) => $this->getRoleName($r), $roles);
    }

    /**
     * Get the role model class.
     *
     * @return class-string<Role>
     */
    protected function getRoleClass(): string
    {
        return config('mandate.models.role', Role::class);
    }
}
