<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\CodeFirst;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

/**
 * Caches discovered code-first definitions.
 */
final class DefinitionCache
{
    private const string CACHE_KEY_PREFIX = 'mandate.code_first';

    private CacheRepository $cache;

    private int $expiration;

    public function __construct(CacheManager $cacheManager)
    {
        $store = config('mandate.cache.store');
        $this->cache = $cacheManager->store($store);
        $this->expiration = (int) config('mandate.cache.expiration', 86400);
    }

    /**
     * Get cached permission definitions.
     *
     * @return Collection<int, PermissionDefinition>|null
     */
    public function getPermissions(): ?Collection
    {
        $data = $this->cache->get(self::CACHE_KEY_PREFIX.'.permissions');

        if ($data === null) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $data */
        return collect($data)->map(fn (array $item) => PermissionDefinition::fromAttributes($item));
    }

    /**
     * Get cached role definitions.
     *
     * @return Collection<int, RoleDefinition>|null
     */
    public function getRoles(): ?Collection
    {
        $data = $this->cache->get(self::CACHE_KEY_PREFIX.'.roles');

        if ($data === null) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $data */
        return collect($data)->map(fn (array $item) => RoleDefinition::fromAttributes($item));
    }

    /**
     * Get cached capability definitions.
     *
     * @return Collection<int, CapabilityDefinition>|null
     */
    public function getCapabilities(): ?Collection
    {
        $data = $this->cache->get(self::CACHE_KEY_PREFIX.'.capabilities');

        if ($data === null) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $data */
        return collect($data)->map(fn (array $item) => CapabilityDefinition::fromAttributes($item));
    }

    /**
     * Store definitions in cache.
     *
     * @param  Collection<int, PermissionDefinition>  $permissions
     * @param  Collection<int, RoleDefinition>  $roles
     * @param  Collection<int, CapabilityDefinition>  $capabilities
     */
    public function store(
        Collection $permissions,
        Collection $roles,
        Collection $capabilities
    ): void {
        $this->cache->put(
            self::CACHE_KEY_PREFIX.'.permissions',
            $permissions->map(fn (PermissionDefinition $p) => $this->serializePermission($p))->all(),
            $this->expiration
        );

        $this->cache->put(
            self::CACHE_KEY_PREFIX.'.roles',
            $roles->map(fn (RoleDefinition $r) => $this->serializeRole($r))->all(),
            $this->expiration
        );

        $this->cache->put(
            self::CACHE_KEY_PREFIX.'.capabilities',
            $capabilities->map(fn (CapabilityDefinition $c) => $this->serializeCapability($c))->all(),
            $this->expiration
        );
    }

    /**
     * Store permission definitions in cache.
     *
     * @param  Collection<int, PermissionDefinition>  $permissions
     */
    public function storePermissions(Collection $permissions): void
    {
        $this->cache->put(
            self::CACHE_KEY_PREFIX.'.permissions',
            $permissions->map(fn (PermissionDefinition $p) => $this->serializePermission($p))->all(),
            $this->expiration
        );
    }

    /**
     * Store role definitions in cache.
     *
     * @param  Collection<int, RoleDefinition>  $roles
     */
    public function storeRoles(Collection $roles): void
    {
        $this->cache->put(
            self::CACHE_KEY_PREFIX.'.roles',
            $roles->map(fn (RoleDefinition $r) => $this->serializeRole($r))->all(),
            $this->expiration
        );
    }

    /**
     * Store capability definitions in cache.
     *
     * @param  Collection<int, CapabilityDefinition>  $capabilities
     */
    public function storeCapabilities(Collection $capabilities): void
    {
        $this->cache->put(
            self::CACHE_KEY_PREFIX.'.capabilities',
            $capabilities->map(fn (CapabilityDefinition $c) => $this->serializeCapability($c))->all(),
            $this->expiration
        );
    }

    /**
     * Clear all cached definitions.
     */
    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY_PREFIX.'.permissions');
        $this->cache->forget(self::CACHE_KEY_PREFIX.'.roles');
        $this->cache->forget(self::CACHE_KEY_PREFIX.'.capabilities');
    }

    /**
     * Serialize a permission definition for caching.
     *
     * @return array<string, mixed>
     */
    private function serializePermission(PermissionDefinition $permission): array
    {
        return [
            'name' => $permission->name,
            'guard' => $permission->guard,
            'label' => $permission->label,
            'description' => $permission->description,
            'context' => $permission->contextClass,
            'capabilities' => $permission->capabilities,
            'source_class' => $permission->sourceClass,
            'source_constant' => $permission->sourceConstant,
        ];
    }

    /**
     * Serialize a role definition for caching.
     *
     * @return array<string, mixed>
     */
    private function serializeRole(RoleDefinition $role): array
    {
        return [
            'name' => $role->name,
            'guard' => $role->guard,
            'label' => $role->label,
            'description' => $role->description,
            'context' => $role->contextClass,
            'source_class' => $role->sourceClass,
            'source_constant' => $role->sourceConstant,
        ];
    }

    /**
     * Serialize a capability definition for caching.
     *
     * @return array<string, mixed>
     */
    private function serializeCapability(CapabilityDefinition $capability): array
    {
        return [
            'name' => $capability->name,
            'guard' => $capability->guard,
            'label' => $capability->label,
            'description' => $capability->description,
            'source_class' => $capability->sourceClass,
            'source_constant' => $capability->sourceConstant,
        ];
    }
}
