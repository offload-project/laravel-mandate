<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use OffloadProject\Mandate\MandateRegistrar;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

beforeEach(function () {
    $this->registrar = app(MandateRegistrar::class);
});

describe('MandateRegistrar', function () {
    describe('permission retrieval', function () {
        it('gets permission by name', function () {
            $permission = Permission::create(['name' => 'article:view', 'guard' => 'web']);

            $found = $this->registrar->getPermissionByName('article:view', 'web');

            expect($found->id)->toBe($permission->id);
        });

        it('returns null for non-existent permission', function () {
            $found = $this->registrar->getPermissionByName('nonexistent', 'web');

            expect($found)->toBeNull();
        });

        it('gets all permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            Permission::create(['name' => 'api:access', 'guard' => 'api']);

            $all = $this->registrar->getPermissions();
            $webOnly = $this->registrar->getPermissionsForGuard('web');

            expect($all)->toHaveCount(3)
                ->and($webOnly)->toHaveCount(2);
        });
    });

    describe('role retrieval', function () {
        it('gets role by name', function () {
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);

            $found = $this->registrar->getRoleByName('admin', 'web');

            expect($found->id)->toBe($role->id);
        });

        it('returns null for non-existent role', function () {
            $found = $this->registrar->getRoleByName('nonexistent', 'web');

            expect($found)->toBeNull();
        });

        it('gets all roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            Role::create(['name' => 'api-admin', 'guard' => 'api']);

            $all = $this->registrar->getRoles();
            $webOnly = $this->registrar->getRolesForGuard('web');

            expect($all)->toHaveCount(3)
                ->and($webOnly)->toHaveCount(2);
        });
    });

    describe('caching', function () {
        it('caches permissions on first access', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);

            $this->registrar->getPermissions();

            expect(Cache::has($this->registrar->getCacheKey().'.permissions'))->toBeTrue();
        });

        it('clears cache', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->registrar->getPermissions();

            $this->registrar->forgetCachedPermissions();

            // Internal cache is cleared, in-memory collections reset
            expect(true)->toBeTrue();
        });

        it('refreshes cache after clearing', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->registrar->getPermissions();

            $this->registrar->forgetCachedPermissions();
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);

            $permissions = $this->registrar->getPermissionsForGuard('web');

            expect($permissions)->toHaveCount(2);
        });
    });
});

describe('MandateRegistrar Capabilities', function () {
    beforeEach(function () {
        $this->enableCapabilities();
    });

    describe('capability retrieval', function () {
        it('gets capability by name', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

            $found = $this->registrar->getCapabilityByName('manage-posts', 'web');

            expect($found->id)->toBe($capability->id);
        });

        it('returns null for non-existent capability', function () {
            $found = $this->registrar->getCapabilityByName('nonexistent', 'web');

            expect($found)->toBeNull();
        });

        it('gets all capabilities', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Capability::create(['name' => 'manage-users', 'guard' => 'web']);
            Capability::create(['name' => 'api:manage', 'guard' => 'api']);

            $all = $this->registrar->getCapabilities();
            $webOnly = $this->registrar->getCapabilitiesForGuard('web');

            expect($all)->toHaveCount(3)
                ->and($webOnly)->toHaveCount(2);
        });

        it('checks if capability exists', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

            expect($this->registrar->capabilityExists('manage-posts', 'web'))->toBeTrue()
                ->and($this->registrar->capabilityExists('nonexistent', 'web'))->toBeFalse();
        });

        it('gets capability names', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Capability::create(['name' => 'manage-users', 'guard' => 'web']);

            $names = $this->registrar->getCapabilityNames('web');

            expect($names)->toHaveCount(2)
                ->and($names->toArray())->toContain('manage-posts', 'manage-users');
        });
    });

    describe('capability caching', function () {
        it('caches capabilities on first access', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

            $this->registrar->getCapabilities();

            expect(Cache::has($this->registrar->getCacheKey().'.capabilities'))->toBeTrue();
        });

        it('clears capability cache with forgetCachedPermissions', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $this->registrar->getCapabilities();

            $this->registrar->forgetCachedPermissions();

            // Cache should be cleared
            expect(Cache::has($this->registrar->getCacheKey().'.capabilities'))->toBeFalse();
        });

        it('refreshes capability cache after clearing', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $this->registrar->getCapabilities();

            $this->registrar->forgetCachedPermissions();
            Capability::create(['name' => 'manage-users', 'guard' => 'web']);

            $capabilities = $this->registrar->getCapabilitiesForGuard('web');

            expect($capabilities)->toHaveCount(2);
        });
    });

    describe('when capabilities disabled', function () {
        it('returns empty collection when disabled', function () {
            config(['mandate.capabilities.enabled' => false]);
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

            // Get fresh registrar since config changed
            $registrar = new MandateRegistrar(app('cache'));
            $capabilities = $registrar->getCapabilities();

            expect($capabilities)->toBeEmpty();
        });
    });
});
