<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Concerns\HasRoles;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

/**
 * Main service class for Mandate.
 *
 * Provides a convenient API for working with permissions and roles.
 *
 * @example
 * // Via Facade
 * Mandate::hasPermission($subject, 'article:edit');
 * Mandate::hasRole($subject, 'admin');
 * Mandate::getPermissions($subject);
 * Mandate::getRoles($subject);
 *
 * // Create permissions and roles
 * Mandate::createPermission('article:edit');
 * Mandate::createRole('admin');
 */
final class Mandate
{
    public function __construct(
        private readonly MandateRegistrar $registrar
    ) {}

    /**
     * Check if a model has a specific permission.
     */
    public function hasPermission(Model $subject, string $permission): bool
    {
        if (! method_exists($subject, 'hasPermission')) {
            return false;
        }

        return $subject->hasPermission($permission);
    }

    /**
     * Check if a model has any of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAnyPermission(Model $subject, array $permissions): bool
    {
        if (! method_exists($subject, 'hasAnyPermission')) {
            return false;
        }

        return $subject->hasAnyPermission($permissions);
    }

    /**
     * Check if a model has all of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    public function hasAllPermissions(Model $subject, array $permissions): bool
    {
        if (! method_exists($subject, 'hasAllPermissions')) {
            return false;
        }

        return $subject->hasAllPermissions($permissions);
    }

    /**
     * Check if a model has a specific role.
     */
    public function hasRole(Model $subject, string $role): bool
    {
        if (! method_exists($subject, 'hasRole')) {
            return false;
        }

        return $subject->hasRole($role);
    }

    /**
     * Check if a model has any of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(Model $subject, array $roles): bool
    {
        if (! method_exists($subject, 'hasAnyRole')) {
            return false;
        }

        return $subject->hasAnyRole($roles);
    }

    /**
     * Check if a model has all of the given roles.
     *
     * @param  array<string>  $roles
     */
    public function hasAllRoles(Model $subject, array $roles): bool
    {
        if (! method_exists($subject, 'hasAllRoles')) {
            return false;
        }

        return $subject->hasAllRoles($roles);
    }

    /**
     * Get all permissions for a model.
     *
     * @return Collection<int, PermissionContract>
     */
    public function getPermissions(Model $subject): Collection
    {
        if (! method_exists($subject, 'getAllPermissions')) {
            return collect();
        }

        return $subject->getAllPermissions();
    }

    /**
     * Get permission names for a model.
     *
     * @return Collection<int, string>
     */
    public function getPermissionNames(Model $subject): Collection
    {
        if (! method_exists($subject, 'getPermissionNames')) {
            return collect();
        }

        return $subject->getPermissionNames();
    }

    /**
     * Get all roles for a model.
     *
     * @return Collection<int, RoleContract>
     */
    public function getRoles(Model $subject): Collection
    {
        if (! property_exists($subject, 'roles') && ! method_exists($subject, 'roles')) {
            return collect();
        }

        /* @var $subject HasRoles */
        return $subject->roles;
    }

    /**
     * Get role names for a model.
     *
     * @return Collection<int, string>
     */
    public function getRoleNames(Model $subject): Collection
    {
        if (! method_exists($subject, 'getRoleNames')) {
            return collect();
        }

        return $subject->getRoleNames();
    }

    /**
     * Create a new permission.
     */
    public function createPermission(string $name, ?string $guard = null): PermissionContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        return $permissionClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);
    }

    /**
     * Find or create a permission.
     */
    public function findOrCreatePermission(string $name, ?string $guard = null): PermissionContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        return $permissionClass::findOrCreate($name, $guard);
    }

    /**
     * Create a new role.
     */
    public function createRole(string $name, ?string $guard = null): RoleContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        return $roleClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);
    }

    /**
     * Find or create a role.
     */
    public function findOrCreateRole(string $name, ?string $guard = null): RoleContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        return $roleClass::findOrCreate($name, $guard);
    }

    /**
     * Get all registered permissions.
     *
     * @return Collection<int, Permission>
     */
    public function getAllPermissions(?string $guard = null): Collection
    {
        if ($guard !== null) {
            return $this->registrar->getPermissionsForGuard($guard);
        }

        return $this->registrar->getPermissions();
    }

    /**
     * Get all registered roles.
     *
     * @return Collection<int, Role>
     */
    public function getAllRoles(?string $guard = null): Collection
    {
        if ($guard !== null) {
            return $this->registrar->getRolesForGuard($guard);
        }

        return $this->registrar->getRoles();
    }

    /**
     * Clear the permission cache.
     */
    public function clearCache(): bool
    {
        return $this->registrar->forgetCachedPermissions();
    }

    /**
     * Get the registrar instance.
     */
    public function getRegistrar(): MandateRegistrar
    {
        return $this->registrar;
    }

    /**
     * Get authorization data for sharing with frontend.
     *
     * @return array{permissions: array<string>, roles: array<string>}
     */
    public function getAuthorizationData(?Authenticatable $subject = null): array
    {
        $subject ??= auth()->user();

        if ($subject === null || ! $subject instanceof Model) {
            return [
                'permissions' => [],
                'roles' => [],
            ];
        }

        return [
            'permissions' => $this->getPermissionNames($subject)->toArray(),
            'roles' => $this->getRoleNames($subject)->toArray(),
        ];
    }
}
