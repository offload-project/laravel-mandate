<?php

declare(strict_types=1);

use OffloadProject\Mandate\Exceptions\PermissionAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\PermissionNotFoundException;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('Permission Model', function () {
    it('can create a permission', function () {
        $permission = Permission::create([
            'name' => 'article:edit',
            'guard' => 'web',
        ]);

        expect($permission)->toBeInstanceOf(Permission::class)
            ->and($permission->name)->toBe('article:edit')
            ->and($permission->guard)->toBe('web');
    });

    it('uses default guard when not specified', function () {
        $permission = Permission::create(['name' => 'article:view']);

        expect($permission->guard)->toBe('web');
    });

    it('can find permission by name', function () {
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        $found = Permission::findByName('article:view', 'web');

        expect($found)->toBeInstanceOf(Permission::class)
            ->and($found->name)->toBe('article:view');
    });

    it('throws exception when permission not found by name', function () {
        Permission::findByName('nonexistent', 'web');
    })->throws(PermissionNotFoundException::class);

    it('can find permission by id', function () {
        $permission = Permission::create(['name' => 'article:view', 'guard' => 'web']);

        $found = Permission::findById($permission->id, 'web');

        expect($found->id)->toBe($permission->id);
    });

    it('throws exception when permission not found by id', function () {
        Permission::findById(999, 'web');
    })->throws(PermissionNotFoundException::class);

    it('can find or create a permission', function () {
        $permission = Permission::findOrCreate('article:delete', 'web');

        expect($permission)->toBeInstanceOf(Permission::class)
            ->and($permission->name)->toBe('article:delete');

        $same = Permission::findOrCreate('article:delete', 'web');

        expect($same->id)->toBe($permission->id);
    });

    it('prevents duplicate permission names per guard', function () {
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        Permission::create(['name' => 'article:view', 'guard' => 'web']);
    })->throws(PermissionAlreadyExistsException::class);

    it('allows same permission name on different guards', function () {
        $webPermission = Permission::create(['name' => 'article:view', 'guard' => 'web']);
        $apiPermission = Permission::create(['name' => 'article:view', 'guard' => 'api']);

        expect($webPermission->id)->not->toBe($apiPermission->id)
            ->and($webPermission->guard)->toBe('web')
            ->and($apiPermission->guard)->toBe('api');
    });

    it('has roles relationship', function () {
        $permission = Permission::create(['name' => 'article:view', 'guard' => 'web']);
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);

        $role->grantPermission($permission);

        expect($permission->roles)->toHaveCount(1)
            ->and($permission->roles->first()->name)->toBe('editor');
    });
});
