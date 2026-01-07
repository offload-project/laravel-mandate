<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Events\RoleAssigned;
use OffloadProject\Mandate\Events\RoleRemoved;
use OffloadProject\Mandate\Guard;
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
        return $this->morphToMany(
            config('mandate.models.role', Role::class),
            'subject',
            config('mandate.tables.role_subject', 'role_subject'),
            config('mandate.column_names.subject_morph_key', 'subject_id'),
            config('mandate.column_names.role_id', 'role_id')
        )->withTimestamps();
    }

    /**
     * Assign one or more roles to this model.
     *
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @return $this
     */
    public function assignRole(string|BackedEnum|RoleContract|array $roles): static
    {
        $roleNames = $this->collectRoleNames($roles);
        $normalizedIds = $this->normalizeRoles($roles);

        $this->roles()->syncWithoutDetaching($normalizedIds);

        $this->forgetPermissionCache();

        if (config('mandate.events', false)) {
            RoleAssigned::dispatch($this, $roleNames);
        }

        return $this;
    }

    /**
     * Alias for assignRole() - assign multiple roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @return $this
     */
    public function assignRoles(array $roles): static
    {
        return $this->assignRole($roles);
    }

    /**
     * Remove one or more roles from this model.
     *
     * @param  string|BackedEnum|RoleContract|array<string|BackedEnum|RoleContract>  $roles
     * @return $this
     */
    public function removeRole(string|BackedEnum|RoleContract|array $roles): static
    {
        $roleNames = $this->collectRoleNames($roles);
        $normalizedIds = $this->normalizeRoles($roles);

        $this->roles()->detach($normalizedIds);

        $this->forgetPermissionCache();

        if (config('mandate.events', false)) {
            RoleRemoved::dispatch($this, $roleNames);
        }

        return $this;
    }

    /**
     * Alias for removeRole() - remove multiple roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @return $this
     */
    public function removeRoles(array $roles): static
    {
        return $this->removeRole($roles);
    }

    /**
     * Sync roles on this model (replace all existing).
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     * @return $this
     */
    public function syncRoles(array $roles): static
    {
        $normalized = $this->normalizeRoles($roles);

        $this->roles()->sync($normalized);

        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * Check if the model has a specific role.
     */
    public function hasRole(string|BackedEnum|RoleContract $role): bool
    {
        $roleName = $this->getRoleName($role);
        $guardName = $this->getGuardName();

        // If roles are already loaded, check in-memory (avoids N+1)
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn ($r) => $r->name === $roleName && $r->guard === $guardName
            );
        }

        return $this->roles()
            ->where('name', $roleName)
            ->where('guard', $guardName)
            ->exists();
    }

    /**
     * Check if the model has any of the given roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $roleNames = array_map(fn ($r) => $this->getRoleName($r), $roles);
        $guardName = $this->getGuardName();

        // If roles are already loaded, check in-memory
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn ($r) => in_array($r->name, $roleNames, true) && $r->guard === $guardName
            );
        }

        return $this->roles()
            ->whereIn('name', $roleNames)
            ->where('guard', $guardName)
            ->exists();
    }

    /**
     * Check if the model has all of the given roles.
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        $roleNames = array_map(fn ($r) => $this->getRoleName($r), $roles);
        $guardName = $this->getGuardName();

        // If roles are already loaded, check in-memory
        if ($this->relationLoaded('roles')) {
            $matchCount = $this->roles->filter(
                fn ($r) => in_array($r->name, $roleNames, true) && $r->guard === $guardName
            )->count();

            return $matchCount === count($roleNames);
        }

        return $this->roles()
            ->whereIn('name', $roleNames)
            ->where('guard', $guardName)
            ->count() === count($roleNames);
    }

    /**
     * Check if the model has exactly the given roles (no more, no less).
     *
     * @param  array<string|BackedEnum|RoleContract>  $roles
     */
    public function hasExactRoles(array $roles): bool
    {
        $guard = $this->getGuardName();
        $assignedRoles = $this->roles()
            ->where('guard', $guard)
            ->pluck('name')
            ->sort()
            ->values();

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
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    /**
     * Check if the model has a permission via one of its roles.
     */
    public function hasPermissionViaRole(string $permission): bool
    {
        $guardName = $this->getGuardName();

        // If roles with permissions are already loaded, check in-memory
        if ($this->relationLoaded('roles')) {
            foreach ($this->roles as $role) {
                if ($role->relationLoaded('permissions')) {
                    if ($role->permissions->contains(
                        fn ($p) => $p->name === $permission && $p->guard === $guardName
                    )) {
                        return true;
                    }
                } else {
                    // Fall back to single query if permissions not loaded
                    return $this->roles()
                        ->whereHas('permissions', fn ($q) => $q->where('name', $permission)->where('guard', $guardName))
                        ->exists();
                }
            }

            return false;
        }

        // Use a single query instead of N+1
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission)->where('guard', $guardName))
            ->exists();
    }

    /**
     * Get all permissions granted via roles.
     *
     * @return Collection<int, PermissionContract>
     */
    public function getPermissionsViaRoles(): Collection
    {
        // Eager load permissions in a single query to avoid N+1
        $roles = $this->relationLoaded('roles') && $this->roles->every(fn ($r) => $r->relationLoaded('permissions'))
            ? $this->roles
            : $this->roles()->with('permissions')->get();

        return $roles->flatMap->permissions->unique('id')->values();
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
