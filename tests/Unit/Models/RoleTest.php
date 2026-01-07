<?php

declare(strict_types=1);

use OffloadProject\Mandate\Exceptions\GuardMismatchException;
use OffloadProject\Mandate\Exceptions\RoleAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\RoleNotFoundException;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('Role Model', function () {
    it('can create a role', function () {
        $role = Role::create([
            'name' => 'admin',
            'guard' => 'web',
        ]);

        expect($role)->toBeInstanceOf(Role::class)
            ->and($role->name)->toBe('admin')
            ->and($role->guard)->toBe('web');
    });

    it('uses default guard when not specified', function () {
        $role = Role::create(['name' => 'editor']);

        expect($role->guard)->toBe('web');
    });

    it('can find role by name', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $found = Role::findByName('admin', 'web');

        expect($found)->toBeInstanceOf(Role::class)
            ->and($found->name)->toBe('admin');
    });

    it('throws exception when role not found by name', function () {
        Role::findByName('nonexistent', 'web');
    })->throws(RoleNotFoundException::class);

    it('can find role by id', function () {
        $role = Role::create(['name' => 'admin', 'guard' => 'web']);

        $found = Role::findById($role->id, 'web');

        expect($found->id)->toBe($role->id);
    });

    it('throws exception when role not found by id', function () {
        Role::findById(999, 'web');
    })->throws(RoleNotFoundException::class);

    it('can find or create a role', function () {
        $role = Role::findOrCreate('moderator', 'web');

        expect($role)->toBeInstanceOf(Role::class)
            ->and($role->name)->toBe('moderator');

        $same = Role::findOrCreate('moderator', 'web');

        expect($same->id)->toBe($role->id);
    });

    it('prevents duplicate role names per guard', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        Role::create(['name' => 'admin', 'guard' => 'web']);
    })->throws(RoleAlreadyExistsException::class);

    it('allows same role name on different guards', function () {
        $webRole = Role::create(['name' => 'admin', 'guard' => 'web']);
        $apiRole = Role::create(['name' => 'admin', 'guard' => 'api']);

        expect($webRole->id)->not->toBe($apiRole->id)
            ->and($webRole->guard)->toBe('web')
            ->and($apiRole->guard)->toBe('api');
    });

    it('can give permission to role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        $role->grantPermission($permission);

        expect($role->permissions)->toHaveCount(1)
            ->and($role->permissions->first()->name)->toBe('article:edit');
    });

    it('can give multiple permissions to role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        Permission::create(['name' => 'article:delete', 'guard' => 'web']);

        $role->grantPermission(['article:edit', 'article:delete']);

        expect($role->permissions)->toHaveCount(2);
    });

    it('can revoke permission from role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        $role->grantPermission($permission);
        expect($role->permissions)->toHaveCount(1);

        $role->revokePermission($permission);
        $role->refresh();

        expect($role->permissions)->toHaveCount(0);
    });

    it('can sync permissions on role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        Permission::create(['name' => 'article:delete', 'guard' => 'web']);

        $role->grantPermission(['article:view', 'article:edit']);
        expect($role->permissions)->toHaveCount(2);

        $role->syncPermissions(['article:delete']);
        $role->refresh();

        expect($role->permissions)->toHaveCount(1)
            ->and($role->permissions->first()->name)->toBe('article:delete');
    });

    it('can check if role has permission', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        expect($role->hasPermission('article:edit'))->toBeFalse();

        $role->grantPermission($permission);

        expect($role->hasPermission('article:edit'))->toBeTrue();
    });

    it('prevents giving permission from different guard', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'article:edit', 'guard' => 'api']);

        $role->grantPermission($permission);
    })->throws(GuardMismatchException::class);
});
