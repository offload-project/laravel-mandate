<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

describe('HasPermissions Trait', function () {
    describe('granting permissions', function () {
        it('can grant a permission by name', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);

            $this->user->grantPermission('article:view');

            expect($this->user->permissions)->toHaveCount(1)
                ->and($this->user->permissions->first()->name)->toBe('article:view');
        });

        it('can grant a permission by model', function () {
            $permission = Permission::create(['name' => 'article:view', 'guard' => 'web']);

            $this->user->grantPermission($permission);

            expect($this->user->permissions)->toHaveCount(1);
        });

        it('can grant multiple permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);

            $this->user->grantPermissions(['article:view', 'article:edit']);

            expect($this->user->permissions)->toHaveCount(2);
        });

        it('does not duplicate permissions when granting same permission twice', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);

            $this->user->grantPermission('article:view');
            $this->user->grantPermission('article:view');

            expect($this->user->permissions)->toHaveCount(1);
        });
    });

    describe('revoking permissions', function () {
        it('can revoke a permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            $this->user->revokePermission('article:view');
            $this->user->refresh();

            expect($this->user->permissions)->toHaveCount(0);
        });

        it('can revoke multiple permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermissions(['article:view', 'article:edit']);

            $this->user->revokePermissions(['article:view', 'article:edit']);
            $this->user->refresh();

            expect($this->user->permissions)->toHaveCount(0);
        });
    });

    describe('syncing permissions', function () {
        it('can sync permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            Permission::create(['name' => 'article:delete', 'guard' => 'web']);

            $this->user->grantPermissions(['article:view', 'article:edit']);
            expect($this->user->permissions)->toHaveCount(2);

            $this->user->syncPermissions(['article:delete']);
            $this->user->refresh();

            expect($this->user->permissions)->toHaveCount(1)
                ->and($this->user->permissions->first()->name)->toBe('article:delete');
        });

        it('removes all permissions when syncing empty array', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            $this->user->syncPermissions([]);
            $this->user->refresh();

            expect($this->user->permissions)->toHaveCount(0);
        });
    });

    describe('checking permissions', function () {
        it('can check if user has direct permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);

            expect($this->user->hasDirectPermission('article:view'))->toBeFalse();

            $this->user->grantPermission('article:view');

            expect($this->user->hasDirectPermission('article:view'))->toBeTrue();
        });

        it('can check if user has permission via role', function () {
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $role->grantPermission($permission);

            $this->user->assignRole($role);

            expect($this->user->hasPermissionViaRole('article:edit'))->toBeTrue()
                ->and($this->user->hasDirectPermission('article:edit'))->toBeFalse();
        });

        it('hasPermission checks both direct and role permissions', function () {
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $editPermission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $role->grantPermission($editPermission);

            $this->user->assignRole($role);
            $this->user->grantPermission('article:view');

            expect($this->user->hasPermission('article:view'))->toBeTrue()
                ->and($this->user->hasPermission('article:edit'))->toBeTrue()
                ->and($this->user->hasPermission('article:delete'))->toBeFalse();
        });

        it('can check if user has any permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            expect($this->user->hasAnyPermission(['article:view', 'article:edit']))->toBeTrue()
                ->and($this->user->hasAnyPermission(['article:delete']))->toBeFalse();
        });

        it('can check if user has all permissions', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermissions(['article:view', 'article:edit']);

            expect($this->user->hasAllPermissions(['article:view', 'article:edit']))->toBeTrue()
                ->and($this->user->hasAllPermissions(['article:view', 'article:delete']))->toBeFalse();
        });
    });

    describe('getting permissions', function () {
        it('can get all permission names', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $this->user->grantPermissions(['article:view', 'article:edit']);

            $names = $this->user->getPermissionNames();

            expect($names)->toHaveCount(2)
                ->and($names->toArray())->toContain('article:view', 'article:edit');
        });

        it('can get all permissions including from roles', function () {
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $editPermission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $role->grantPermission($editPermission);

            $this->user->assignRole($role);
            $this->user->grantPermission('article:view');

            $allPermissions = $this->user->getAllPermissions();

            expect($allPermissions)->toHaveCount(2);
        });
    });

    describe('query scopes', function () {
        it('can query users with permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            $anotherUser = User::create(['name' => 'Another', 'email' => 'another@example.com']);

            $users = User::permission('article:view')->get();

            expect($users)->toHaveCount(1)
                ->and($users->first()->id)->toBe($this->user->id);
        });

        it('can query users without permission', function () {
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            $this->user->grantPermission('article:view');

            $anotherUser = User::create(['name' => 'Another', 'email' => 'another@example.com']);

            $users = User::withoutPermission('article:view')->get();

            expect($users)->toHaveCount(1)
                ->and($users->first()->id)->toBe($anotherUser->id);
        });
    });
});

describe('HasPermissions with Wildcards', function () {
    beforeEach(function () {
        $this->enableWildcards();
    });

    it('matches wildcard permissions', function () {
        Permission::create(['name' => 'article:*', 'guard' => 'web']);
        $this->user->grantPermission('article:*');

        expect($this->user->hasPermission('article:view'))->toBeTrue()
            ->and($this->user->hasPermission('article:edit'))->toBeTrue()
            ->and($this->user->hasPermission('users:view'))->toBeFalse();
    });

    it('matches universal wildcard', function () {
        Permission::create(['name' => '*', 'guard' => 'web']);
        $this->user->grantPermission('*');

        expect($this->user->hasPermission('article:view'))->toBeTrue()
            ->and($this->user->hasPermission('anything'))->toBeTrue();
    });
});
