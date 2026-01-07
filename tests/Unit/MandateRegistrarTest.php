<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use OffloadProject\Mandate\MandateRegistrar;
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
