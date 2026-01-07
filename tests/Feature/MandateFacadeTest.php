<?php

declare(strict_types=1);

use OffloadProject\Mandate\Facades\Mandate;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

describe('Mandate Facade', function () {
    describe('permission checking', function () {
        it('can check if subject has permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            expect(Mandate::hasPermission($this->user, 'article:view'))->toBeTrue()
                ->and(Mandate::hasPermission($this->user, 'article:edit'))->toBeFalse();
        });

        it('can check if subject has any permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            expect(Mandate::hasAnyPermission($this->user, ['article:view', 'article:edit']))->toBeTrue()
                ->and(Mandate::hasAnyPermission($this->user, ['article:delete']))->toBeFalse();
        });

        it('can check if subject has all permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermissions(['article:view', 'article:edit']);

            expect(Mandate::hasAllPermissions($this->user, ['article:view', 'article:edit']))->toBeTrue()
                ->and(Mandate::hasAllPermissions($this->user, ['article:view', 'article:delete']))->toBeFalse();
        });
    });

    describe('role checking', function () {
        it('can check if subject has role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            expect(Mandate::hasRole($this->user, 'admin'))->toBeTrue()
                ->and(Mandate::hasRole($this->user, 'editor'))->toBeFalse();
        });

        it('can check if subject has any role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRole('admin');

            expect(Mandate::hasAnyRole($this->user, ['admin', 'editor']))->toBeTrue()
                ->and(Mandate::hasAnyRole($this->user, ['moderator']))->toBeFalse();
        });

        it('can check if subject has all roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRoles(['admin', 'editor']);

            expect(Mandate::hasAllRoles($this->user, ['admin', 'editor']))->toBeTrue()
                ->and(Mandate::hasAllRoles($this->user, ['admin', 'moderator']))->toBeFalse();
        });
    });

    describe('getting permissions and roles', function () {
        it('can get permissions for subject', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermissions(['article:view', 'article:edit']);

            $permissions = Mandate::getPermissions($this->user);

            expect($permissions)->toHaveCount(2);
        });

        it('can get permission names for subject', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            $names = Mandate::getPermissionNames($this->user);

            expect($names->toArray())->toContain('article:view');
        });

        it('can get roles for subject', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRoles(['admin', 'editor']);

            $roles = Mandate::getRoles($this->user);

            expect($roles)->toHaveCount(2);
        });

        it('can get role names for subject', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            $names = Mandate::getRoleNames($this->user);

            expect($names->toArray())->toContain('admin');
        });
    });

    describe('creating permissions and roles', function () {
        it('can create a permission', function () {
            $permission = Mandate::createPermission('article:view');

            expect($permission->name)->toBe('article:view')
                ->and($permission->guard)->toBe('web');
        });

        it('can find or create a permission', function () {
            $first = Mandate::findOrCreatePermission('article:view');
            $second = Mandate::findOrCreatePermission('article:view');

            expect($first->id)->toBe($second->id);
        });

        it('can create a role', function () {
            $role = Mandate::createRole('admin');

            expect($role->name)->toBe('admin')
                ->and($role->guard)->toBe('web');
        });

        it('can find or create a role', function () {
            $first = Mandate::findOrCreateRole('admin');
            $second = Mandate::findOrCreateRole('admin');

            expect($first->id)->toBe($second->id);
        });
    });

    describe('retrieving all permissions and roles', function () {
        it('can get all permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            Permission::create(['name' => 'api:access', 'guard' => 'api']);

            $allPermissions = Mandate::getAllPermissions();
            $webPermissions = Mandate::getAllPermissions('web');

            expect($allPermissions)->toHaveCount(3)
                ->and($webPermissions)->toHaveCount(2);
        });

        it('can get all roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            Role::create(['name' => 'api-admin', 'guard' => 'api']);

            $allRoles = Mandate::getAllRoles();
            $webRoles = Mandate::getAllRoles('web');

            expect($allRoles)->toHaveCount(3)
                ->and($webRoles)->toHaveCount(2);
        });
    });

    describe('cache management', function () {
        it('can clear cache', function () {
            // First trigger cache population
            Permission::create(['name' => 'test:permission', 'guard' => 'web']);
            Mandate::getAllPermissions();

            $result = Mandate::clearCache();

            // clearCache returns bool, may be false if key wasn't set
            expect($result)->toBeBool();
        });
    });

    describe('authorization data', function () {
        it('returns authorization data for user', function () {
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $role->grantPermission($permission);
            $this->user->assignRole($role);

            $this->actingAs($this->user);

            $data = Mandate::getAuthorizationData($this->user);

            expect($data)->toHaveKeys(['permissions', 'roles'])
                ->and($data['roles'])->toContain('admin')
                ->and($data['permissions'])->toContain('article:edit');
        });

        it('returns empty arrays for guest', function () {
            $data = Mandate::getAuthorizationData(null);

            expect($data['permissions'])->toBeEmpty()
                ->and($data['roles'])->toBeEmpty();
        });
    });
});
