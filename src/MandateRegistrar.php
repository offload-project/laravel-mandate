<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

/**
 * Registry for permissions and roles with caching support.
 *
 * This class manages the loading and caching of all permissions and roles
 * to optimize permission checks throughout the application.
 */
final class MandateRegistrar
{
    /** @var EloquentCollection<int, Permission>|null */
    private ?EloquentCollection $permissions = null;

    /** @var EloquentCollection<int, Role>|null */
    private ?EloquentCollection $roles = null;

    /** @var EloquentCollection<int, Capability>|null */
    private ?EloquentCollection $capabilities = null;

    private CacheRepository $cache;

    private string $cacheKey;

    private int $cacheExpiration;

    /** @var class-string<Permission>|null */
    private ?string $permissionClass = null;

    /** @var class-string<Role>|null */
    private ?string $roleClass = null;

    /** @var class-string<Capability>|null */
    private ?string $capabilityClass = null;

    private ?FeatureAccessHandler $featureHandler = null;

    private bool $featureHandlerResolved = false;

    public function __construct(CacheManager $cacheManager)
    {
        $store = config('mandate.cache.store');
        $this->cache = $cacheManager->store($store);
        $this->cacheKey = config('mandate.cache.key', 'mandate.permissions.cache');
        $this->cacheExpiration = config('mandate.cache.expiration', 86400);
    }

    /**
     * Get all permissions, loading from cache or database.
     *
     * @return EloquentCollection<int, Permission>
     */
    public function getPermissions(): EloquentCollection
    {
        if ($this->permissions === null) {
            $this->permissions = $this->loadPermissionsFromCacheOrDatabase();
        }

        return $this->permissions;
    }

    /**
     * Get permissions for a specific guard.
     *
     * @return EloquentCollection<int, Permission>
     */
    public function getPermissionsForGuard(string $guard): EloquentCollection
    {
        return $this->getPermissions()->filter(
            fn (Permission $permission) => $permission->guard === $guard
        );
    }

    /**
     * Get all roles, loading from cache or database.
     *
     * @return EloquentCollection<int, Role>
     */
    public function getRoles(): EloquentCollection
    {
        if ($this->roles === null) {
            $this->roles = $this->loadRolesFromCacheOrDatabase();
        }

        return $this->roles;
    }

    /**
     * Get roles for a specific guard.
     *
     * @return EloquentCollection<int, Role>
     */
    public function getRolesForGuard(string $guard): EloquentCollection
    {
        return $this->getRoles()->filter(
            fn (Role $role) => $role->guard === $guard
        );
    }

    /**
     * Clear the cached permissions, roles, and capabilities.
     */
    public function forgetCachedPermissions(): bool
    {
        $this->permissions = null;
        $this->roles = null;
        $this->capabilities = null;

        $this->cache->forget($this->cacheKey.'.permissions');
        $this->cache->forget($this->cacheKey.'.roles');
        $this->cache->forget($this->cacheKey.'.capabilities');

        return true;
    }

    /**
     * Get the cache key being used.
     */
    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    /**
     * Get the cache expiration in seconds.
     */
    public function getCacheExpiration(): int
    {
        return $this->cacheExpiration;
    }

    /**
     * Get a permission by name and guard.
     */
    public function getPermissionByName(string $name, string $guard): ?Permission
    {
        return $this->getPermissions()->first(
            fn (Permission $permission) => $permission->name === $name && $permission->guard === $guard
        );
    }

    /**
     * Get a role by name and guard.
     */
    public function getRoleByName(string $name, string $guard): ?Role
    {
        return $this->getRoles()->first(
            fn (Role $role) => $role->name === $name && $role->guard === $guard
        );
    }

    /**
     * Check if a permission exists.
     */
    public function permissionExists(string $name, string $guard): bool
    {
        return $this->getPermissionByName($name, $guard) !== null;
    }

    /**
     * Check if a role exists.
     */
    public function roleExists(string $name, string $guard): bool
    {
        return $this->getRoleByName($name, $guard) !== null;
    }

    /**
     * Get permission names as a collection.
     *
     * @return Collection<int, string>
     */
    public function getPermissionNames(?string $guard = null): Collection
    {
        $permissions = $guard !== null
            ? $this->getPermissionsForGuard($guard)
            : $this->getPermissions();

        return $permissions->pluck('name');
    }

    /**
     * Get role names as a collection.
     *
     * @return Collection<int, string>
     */
    public function getRoleNames(?string $guard = null): Collection
    {
        $roles = $guard !== null
            ? $this->getRolesForGuard($guard)
            : $this->getRoles();

        return $roles->pluck('name');
    }

    /**
     * Get all capabilities, loading from cache or database.
     *
     * @return EloquentCollection<int, Capability>
     */
    public function getCapabilities(): EloquentCollection
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return new EloquentCollection();
        }

        if ($this->capabilities === null) {
            $this->capabilities = $this->loadCapabilitiesFromCacheOrDatabase();
        }

        return $this->capabilities;
    }

    /**
     * Get capabilities for a specific guard.
     *
     * @return EloquentCollection<int, Capability>
     */
    public function getCapabilitiesForGuard(string $guard): EloquentCollection
    {
        return $this->getCapabilities()->filter(
            fn (Capability $capability) => $capability->guard === $guard
        );
    }

    /**
     * Get a capability by name and guard.
     */
    public function getCapabilityByName(string $name, string $guard): ?Capability
    {
        return $this->getCapabilities()->first(
            fn (Capability $capability) => $capability->name === $name && $capability->guard === $guard
        );
    }

    /**
     * Check if a capability exists.
     */
    public function capabilityExists(string $name, string $guard): bool
    {
        return $this->getCapabilityByName($name, $guard) !== null;
    }

    /**
     * Get capability names as a collection.
     *
     * @return Collection<int, string>
     */
    public function getCapabilityNames(?string $guard = null): Collection
    {
        $capabilities = $guard !== null
            ? $this->getCapabilitiesForGuard($guard)
            : $this->getCapabilities();

        return $capabilities->pluck('name');
    }

    /**
     * Get the permission model class (cached).
     *
     * @return class-string<Permission>
     */
    public function getPermissionClass(): string
    {
        return $this->permissionClass ??= config('mandate.models.permission', Permission::class);
    }

    /**
     * Get the role model class (cached).
     *
     * @return class-string<Role>
     */
    public function getRoleClass(): string
    {
        return $this->roleClass ??= config('mandate.models.role', Role::class);
    }

    /**
     * Get the capability model class (cached).
     *
     * @return class-string<Capability>
     */
    public function getCapabilityClass(): string
    {
        return $this->capabilityClass ??= config('mandate.models.capability', Capability::class);
    }

    /**
     * Get the feature access handler (cached).
     *
     * Resolution is cached to avoid repeated container lookups.
     * Returns null if no handler is bound.
     */
    public function getFeatureAccessHandler(): ?FeatureAccessHandler
    {
        if (! $this->featureHandlerResolved) {
            $this->featureHandlerResolved = true;
            $this->featureHandler = app()->bound(FeatureAccessHandler::class)
                ? app(FeatureAccessHandler::class)
                : null;
        }

        return $this->featureHandler;
    }

    /**
     * Load permissions from cache or database.
     *
     * @return EloquentCollection<int, Permission>
     */
    private function loadPermissionsFromCacheOrDatabase(): EloquentCollection
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = $this->cache->remember(
            $this->cacheKey.'.permissions',
            $this->cacheExpiration,
            fn () => $this->loadPermissionsFromDatabase()->toArray()
        );

        $permissionClass = $this->getPermissionClass();

        /** @var Permission $instance */
        $instance = new $permissionClass;

        return $instance->newCollection(
            array_map(
                fn (array $attributes) => (new $permissionClass)->forceFill($attributes),
                $data
            )
        );
    }

    /**
     * Load roles from cache or database.
     *
     * @return EloquentCollection<int, Role>
     */
    private function loadRolesFromCacheOrDatabase(): EloquentCollection
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = $this->cache->remember(
            $this->cacheKey.'.roles',
            $this->cacheExpiration,
            fn () => $this->loadRolesFromDatabase()->toArray()
        );

        $roleClass = $this->getRoleClass();

        /** @var Role $instance */
        $instance = new $roleClass;

        return $instance->newCollection(
            array_map(
                fn (array $attributes) => (new $roleClass)->forceFill($attributes),
                $data
            )
        );
    }

    /**
     * Load permissions directly from database.
     *
     * @return EloquentCollection<int, Permission>
     */
    private function loadPermissionsFromDatabase(): EloquentCollection
    {
        $permissionClass = $this->getPermissionClass();

        return $permissionClass::query()->get();
    }

    /**
     * Load roles directly from database.
     *
     * @return EloquentCollection<int, Role>
     */
    private function loadRolesFromDatabase(): EloquentCollection
    {
        $roleClass = $this->getRoleClass();

        return $roleClass::query()->get();
    }

    /**
     * Load capabilities from cache or database.
     *
     * @return EloquentCollection<int, Capability>
     */
    private function loadCapabilitiesFromCacheOrDatabase(): EloquentCollection
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = $this->cache->remember(
            $this->cacheKey.'.capabilities',
            $this->cacheExpiration,
            fn () => $this->loadCapabilitiesFromDatabase()->toArray()
        );

        $capabilityClass = $this->getCapabilityClass();

        /** @var Capability $instance */
        $instance = new $capabilityClass;

        return $instance->newCollection(
            array_map(
                fn (array $attributes) => (new $capabilityClass)->forceFill($attributes),
                $data
            )
        );
    }

    /**
     * Load capabilities directly from database.
     *
     * @return EloquentCollection<int, Capability>
     */
    private function loadCapabilitiesFromDatabase(): EloquentCollection
    {
        $capabilityClass = $this->getCapabilityClass();

        return $capabilityClass::query()->get();
    }
}
