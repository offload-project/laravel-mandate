<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Concerns\HasRoles;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use RuntimeException;

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
     * Check if context model support is enabled.
     */
    public function contextEnabled(): bool
    {
        return (bool) config('mandate.context.enabled', false);
    }

    /**
     * Check if a model has a specific permission.
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasPermission(Model $subject, string $permission, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasPermission')) {
            return false;
        }

        return $subject->hasPermission($permission, $context);
    }

    /**
     * Check if a model has any of the given permissions.
     *
     * @param  array<string>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasAnyPermission(Model $subject, array $permissions, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAnyPermission')) {
            return false;
        }

        return $subject->hasAnyPermission($permissions, $context);
    }

    /**
     * Check if a model has all of the given permissions.
     *
     * @param  array<string>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasAllPermissions(Model $subject, array $permissions, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAllPermissions')) {
            return false;
        }

        return $subject->hasAllPermissions($permissions, $context);
    }

    /**
     * Check if a model has a specific role.
     *
     * @param  Model|null  $context  Optional context model for scoped role check
     */
    public function hasRole(Model $subject, string $role, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasRole')) {
            return false;
        }

        return $subject->hasRole($role, $context);
    }

    /**
     * Check if a model has any of the given roles.
     *
     * @param  array<string>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     */
    public function hasAnyRole(Model $subject, array $roles, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAnyRole')) {
            return false;
        }

        return $subject->hasAnyRole($roles, $context);
    }

    /**
     * Check if a model has all of the given roles.
     *
     * @param  array<string>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     */
    public function hasAllRoles(Model $subject, array $roles, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAllRoles')) {
            return false;
        }

        return $subject->hasAllRoles($roles, $context);
    }

    /**
     * Get all permissions for a model.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, PermissionContract>
     */
    public function getPermissions(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getAllPermissions')) {
            return collect();
        }

        return $subject->getAllPermissions($context);
    }

    /**
     * Get permission names for a model.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, string>
     */
    public function getPermissionNames(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getPermissionNames')) {
            return collect();
        }

        return $subject->getPermissionNames($context);
    }

    /**
     * Get all roles for a model.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, RoleContract>
     */
    public function getRoles(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getRolesForContext')) {
            if (! property_exists($subject, 'roles') && ! method_exists($subject, 'roles')) {
                return collect();
            }

            /* @var $subject HasRoles */
            return $subject->roles;
        }

        return $subject->getRolesForContext($context);
    }

    /**
     * Get role names for a model.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, string>
     */
    public function getRoleNames(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getRoleNames')) {
            return collect();
        }

        return $subject->getRoleNames($context);
    }

    /**
     * Get all contexts where a subject has a specific role.
     *
     * @return Collection<int, Model>
     */
    public function getRoleContexts(Model $subject, string $role): Collection
    {
        if (! method_exists($subject, 'getRoleContexts')) {
            return collect();
        }

        return $subject->getRoleContexts($role);
    }

    /**
     * Get all contexts where a subject has a specific permission.
     *
     * @return Collection<int, Model>
     */
    public function getPermissionContexts(Model $subject, string $permission): Collection
    {
        if (! method_exists($subject, 'getPermissionContexts')) {
            return collect();
        }

        return $subject->getPermissionContexts($permission);
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
     * Check if capabilities feature is enabled.
     */
    public function capabilitiesEnabled(): bool
    {
        return (bool) config('mandate.capabilities.enabled', false);
    }

    /**
     * Check if a model has a specific capability.
     */
    public function hasCapability(Model $subject, string $capability): bool
    {
        if (! $this->capabilitiesEnabled()) {
            return false;
        }

        if (! method_exists($subject, 'hasCapability')) {
            return false;
        }

        return $subject->hasCapability($capability);
    }

    /**
     * Check if a model has any of the given capabilities.
     *
     * @param  array<string>  $capabilities
     */
    public function hasAnyCapability(Model $subject, array $capabilities): bool
    {
        if (! $this->capabilitiesEnabled()) {
            return false;
        }

        if (! method_exists($subject, 'hasAnyCapability')) {
            return false;
        }

        return $subject->hasAnyCapability($capabilities);
    }

    /**
     * Check if a model has all of the given capabilities.
     *
     * @param  array<string>  $capabilities
     */
    public function hasAllCapabilities(Model $subject, array $capabilities): bool
    {
        if (! $this->capabilitiesEnabled()) {
            return false;
        }

        if (! method_exists($subject, 'hasAllCapabilities')) {
            return false;
        }

        return $subject->hasAllCapabilities($capabilities);
    }

    /**
     * Get all capabilities for a model.
     *
     * @return Collection<int, CapabilityContract>
     */
    public function getCapabilities(Model $subject): Collection
    {
        if (! $this->capabilitiesEnabled()) {
            return collect();
        }

        if (! method_exists($subject, 'getAllCapabilities')) {
            return collect();
        }

        return $subject->getAllCapabilities();
    }

    /**
     * Get capability names for a model.
     *
     * @return Collection<int, string>
     */
    public function getCapabilityNames(Model $subject): Collection
    {
        if (! $this->capabilitiesEnabled()) {
            return collect();
        }

        if (! method_exists($subject, 'getAllCapabilities')) {
            return collect();
        }

        return $subject->getAllCapabilities()->pluck('name');
    }

    /**
     * Create a new capability.
     */
    public function createCapability(string $name, ?string $guard = null): CapabilityContract
    {
        if (! $this->capabilitiesEnabled()) {
            throw new RuntimeException('Capabilities feature is not enabled. Set mandate.capabilities.enabled to true in your configuration.');
        }

        $guard ??= Guard::getDefaultName();

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        return $capabilityClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);
    }

    /**
     * Find or create a capability.
     */
    public function findOrCreateCapability(string $name, ?string $guard = null): CapabilityContract
    {
        if (! $this->capabilitiesEnabled()) {
            throw new RuntimeException('Capabilities feature is not enabled. Set mandate.capabilities.enabled to true in your configuration.');
        }

        $guard ??= Guard::getDefaultName();

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        return $capabilityClass::findOrCreate($name, $guard);
    }

    /**
     * Get all registered capabilities.
     *
     * @return Collection<int, Capability>
     */
    public function getAllCapabilities(?string $guard = null): Collection
    {
        if (! $this->capabilitiesEnabled()) {
            return collect();
        }

        if ($guard !== null) {
            return $this->registrar->getCapabilitiesForGuard($guard);
        }

        return $this->registrar->getCapabilities();
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
     * @param  Model|null  $context  Optional context model to filter authorization data
     * @return array{permissions: array<string>, roles: array<string>, capabilities?: array<string>}
     */
    public function getAuthorizationData(?Authenticatable $subject = null, ?Model $context = null): array
    {
        $subject ??= auth()->user();

        if ($subject === null || ! $subject instanceof Model) {
            $data = [
                'permissions' => [],
                'roles' => [],
            ];

            if ($this->capabilitiesEnabled()) {
                $data['capabilities'] = [];
            }

            return $data;
        }

        $data = [
            'permissions' => $this->getPermissionNames($subject, $context)->toArray(),
            'roles' => $this->getRoleNames($subject, $context)->toArray(),
        ];

        if ($this->capabilitiesEnabled()) {
            $data['capabilities'] = $this->getCapabilityNames($subject)->toArray();
        }

        return $data;
    }
}
