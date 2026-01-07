<?php

declare(strict_types=1);

use OffloadProject\Mandate\Exceptions\CapabilityAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\CapabilityNotFoundException;
use OffloadProject\Mandate\Exceptions\GuardMismatchException;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

beforeEach(function () {
    $this->enableCapabilities();
});

describe('Capability Model', function () {
    it('can create a capability', function () {
        $capability = Capability::create([
            'name' => 'manage-posts',
            'guard' => 'web',
        ]);

        expect($capability)->toBeInstanceOf(Capability::class)
            ->and($capability->name)->toBe('manage-posts')
            ->and($capability->guard)->toBe('web');
    });

    it('uses default guard when not specified', function () {
        $capability = Capability::create(['name' => 'manage-users']);

        expect($capability->guard)->toBe('web');
    });

    it('can find capability by name', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $found = Capability::findByName('manage-posts', 'web');

        expect($found)->toBeInstanceOf(Capability::class)
            ->and($found->name)->toBe('manage-posts');
    });

    it('throws exception when capability not found by name', function () {
        Capability::findByName('nonexistent', 'web');
    })->throws(CapabilityNotFoundException::class);

    it('can find capability by id', function () {
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $found = Capability::findById($capability->id, 'web');

        expect($found->id)->toBe($capability->id);
    });

    it('throws exception when capability not found by id', function () {
        Capability::findById(999, 'web');
    })->throws(CapabilityNotFoundException::class);

    it('can find or create a capability', function () {
        $capability = Capability::findOrCreate('manage-comments', 'web');

        expect($capability)->toBeInstanceOf(Capability::class)
            ->and($capability->name)->toBe('manage-comments');

        $same = Capability::findOrCreate('manage-comments', 'web');

        expect($same->id)->toBe($capability->id);
    });

    it('prevents duplicate capability names per guard', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
    })->throws(CapabilityAlreadyExistsException::class);

    it('allows same capability name on different guards', function () {
        $webCapability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $apiCapability = Capability::create(['name' => 'manage-posts', 'guard' => 'api']);

        expect($webCapability->id)->not->toBe($apiCapability->id)
            ->and($webCapability->guard)->toBe('web')
            ->and($apiCapability->guard)->toBe('api');
    });

    describe('permissions', function () {
        it('can grant permission to capability', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'web']);

            $capability->grantPermission($permission);

            expect($capability->permissions)->toHaveCount(1)
                ->and($capability->permissions->first()->name)->toBe('posts:edit');
        });

        it('can grant multiple permissions to capability', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Permission::create(['name' => 'posts:view', 'guard' => 'web']);
            Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
            Permission::create(['name' => 'posts:delete', 'guard' => 'web']);

            $capability->grantPermission(['posts:view', 'posts:edit', 'posts:delete']);

            expect($capability->permissions)->toHaveCount(3);
        });

        it('can revoke permission from capability', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'web']);

            $capability->grantPermission($permission);
            expect($capability->permissions)->toHaveCount(1);

            $capability->revokePermission($permission);
            $capability->refresh();

            expect($capability->permissions)->toHaveCount(0);
        });

        it('can sync permissions on capability', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Permission::create(['name' => 'posts:view', 'guard' => 'web']);
            Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
            Permission::create(['name' => 'posts:delete', 'guard' => 'web']);

            $capability->grantPermission(['posts:view', 'posts:edit']);
            expect($capability->permissions)->toHaveCount(2);

            $capability->syncPermissions(['posts:delete']);
            $capability->refresh();

            expect($capability->permissions)->toHaveCount(1)
                ->and($capability->permissions->first()->name)->toBe('posts:delete');
        });

        it('can check if capability has permission', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'web']);

            expect($capability->hasPermission('posts:edit'))->toBeFalse();

            $capability->grantPermission($permission);

            expect($capability->hasPermission('posts:edit'))->toBeTrue();
        });

        it('prevents granting permission from different guard', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'api']);

            $capability->grantPermission($permission);
        })->throws(GuardMismatchException::class);
    });

    describe('roles relationship', function () {
        it('can get roles that have this capability', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);

            $role->assignCapability($capability);

            expect($capability->roles)->toHaveCount(1)
                ->and($capability->roles->first()->name)->toBe('editor');
        });
    });
});
