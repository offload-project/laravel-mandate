<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

describe('HasRoles Trait', function () {
    describe('assigning roles', function () {
        it('can assign a role by name', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->assignRole('admin');

            expect($this->user->roles)->toHaveCount(1)
                ->and($this->user->roles->first()->name)->toBe('admin');
        });

        it('can assign a role by model', function () {
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->assignRole($role);

            expect($this->user->roles)->toHaveCount(1);
        });

        it('can assign multiple roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);

            $this->user->assignRoles(['admin', 'editor']);

            expect($this->user->roles)->toHaveCount(2);
        });

        it('does not duplicate roles when assigning same role twice', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->assignRole('admin');
            $this->user->assignRole('admin');

            expect($this->user->roles)->toHaveCount(1);
        });
    });

    describe('removing roles', function () {
        it('can remove a role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            $this->user->removeRole('admin');
            $this->user->refresh();

            expect($this->user->roles)->toHaveCount(0);
        });

        it('can remove multiple roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRoles(['admin', 'editor']);

            $this->user->removeRoles(['admin', 'editor']);
            $this->user->refresh();

            expect($this->user->roles)->toHaveCount(0);
        });
    });

    describe('syncing roles', function () {
        it('can sync roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            Role::create(['name' => 'moderator', 'guard' => 'web']);

            $this->user->assignRoles(['admin', 'editor']);
            expect($this->user->roles)->toHaveCount(2);

            $this->user->syncRoles(['moderator']);
            $this->user->refresh();

            expect($this->user->roles)->toHaveCount(1)
                ->and($this->user->roles->first()->name)->toBe('moderator');
        });

        it('removes all roles when syncing empty array', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            $this->user->syncRoles([]);
            $this->user->refresh();

            expect($this->user->roles)->toHaveCount(0);
        });
    });

    describe('checking roles', function () {
        it('can check if user has role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);

            expect($this->user->hasRole('admin'))->toBeFalse();

            $this->user->assignRole('admin');

            expect($this->user->hasRole('admin'))->toBeTrue();
        });

        it('can check if user has any role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRole('admin');

            expect($this->user->hasAnyRole(['admin', 'editor']))->toBeTrue()
                ->and($this->user->hasAnyRole(['moderator']))->toBeFalse();
        });

        it('can check if user has all roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRoles(['admin', 'editor']);

            expect($this->user->hasAllRoles(['admin', 'editor']))->toBeTrue()
                ->and($this->user->hasAllRoles(['admin', 'moderator']))->toBeFalse();
        });

        it('can check if user has exact roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            Role::create(['name' => 'moderator', 'guard' => 'web']);

            $this->user->assignRoles(['admin', 'editor']);

            expect($this->user->hasExactRoles(['admin', 'editor']))->toBeTrue()
                ->and($this->user->hasExactRoles(['editor', 'admin']))->toBeTrue()
                ->and($this->user->hasExactRoles(['admin']))->toBeFalse()
                ->and($this->user->hasExactRoles(['admin', 'editor', 'moderator']))->toBeFalse();
        });
    });

    describe('getting roles', function () {
        it('can get all role names', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRoles(['admin', 'editor']);

            $names = $this->user->getRoleNames();

            expect($names)->toHaveCount(2)
                ->and($names->toArray())->toContain('admin', 'editor');
        });
    });

    describe('permissions via roles', function () {
        it('can check if user has permission via role', function () {
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $role->grantPermission($permission);

            $this->user->assignRole($role);

            expect($this->user->hasPermissionViaRole('article:edit'))->toBeTrue()
                ->and($this->user->hasPermissionViaRole('article:delete'))->toBeFalse();
        });

        it('can get permissions via roles', function () {
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            Permission::create(['name' => 'article:view', 'guard' => 'web']);
            Permission::create(['name' => 'article:edit', 'guard' => 'web']);
            $role->grantPermission(['article:view', 'article:edit']);

            $this->user->assignRole($role);

            $permissions = $this->user->getPermissionsViaRoles();

            expect($permissions)->toHaveCount(2);
        });
    });

    describe('query scopes', function () {
        it('can query users with role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            $anotherUser = User::create(['name' => 'Another', 'email' => 'another@example.com']);

            $users = User::role('admin')->get();

            expect($users)->toHaveCount(1)
                ->and($users->first()->id)->toBe($this->user->id);
        });

        it('can query users without role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            $anotherUser = User::create(['name' => 'Another', 'email' => 'another@example.com']);

            $users = User::withoutRole('admin')->get();

            expect($users)->toHaveCount(1)
                ->and($users->first()->id)->toBe($anotherUser->id);
        });

        it('can query users with multiple roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRole('admin');

            $editorUser = User::create(['name' => 'Editor', 'email' => 'editor@example.com']);
            $editorUser->assignRole('editor');

            $regularUser = User::create(['name' => 'Regular', 'email' => 'regular@example.com']);

            $users = User::role(['admin', 'editor'])->get();

            expect($users)->toHaveCount(2);
        });
    });

    describe('model deletion', function () {
        it('detaches roles when model is deleted', function () {
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole($role);

            $pivotTable = config('mandate.tables.role_subject', 'role_subject');
            expect(DB::table($pivotTable)->where('role_id', $role->id)->count())->toBe(1);

            $this->user->delete();

            expect(DB::table($pivotTable)->where('role_id', $role->id)->count())->toBe(0);
        });
    });
});
