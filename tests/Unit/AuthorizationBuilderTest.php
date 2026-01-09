<?php

declare(strict_types=1);

use OffloadProject\Mandate\Facades\Mandate;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\Team;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

describe('AuthorizationBuilder', function () {
    describe('single checks', function () {
        it('can() checks single permission', function () {
            $permission = Permission::create(['name' => 'edit-articles', 'guard' => 'web']);
            $this->user->grantPermission($permission);

            expect(Mandate::for($this->user)->can('edit-articles'))->toBeTrue();
            expect(Mandate::for($this->user)->can('delete-articles'))->toBeFalse();
        });

        it('is() checks single role', function () {
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole($role);

            expect(Mandate::for($this->user)->is('admin'))->toBeTrue();
            expect(Mandate::for($this->user)->is('editor'))->toBeFalse();
        });
    });

    describe('chained permission checks', function () {
        it('hasPermission starts a chain', function () {
            $permission = Permission::create(['name' => 'view', 'guard' => 'web']);
            $this->user->grantPermission($permission);

            expect(Mandate::for($this->user)->hasPermission('view')->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasPermission('edit')->check())->toBeFalse();
        });

        it('andHasPermission requires both permissions', function () {
            Permission::create(['name' => 'view', 'guard' => 'web']);
            Permission::create(['name' => 'edit', 'guard' => 'web']);
            $this->user->grantPermission('view');
            $this->user->grantPermission('edit');

            expect(Mandate::for($this->user)->hasPermission('view')->andHasPermission('edit')->check())->toBeTrue();

            $this->user->revokePermission('edit');

            expect(Mandate::for($this->user)->hasPermission('view')->andHasPermission('edit')->check())->toBeFalse();
        });

        it('orHasPermission requires either permission', function () {
            Permission::create(['name' => 'view', 'guard' => 'web']);
            Permission::create(['name' => 'edit', 'guard' => 'web']);
            $this->user->grantPermission('view');

            expect(Mandate::for($this->user)->hasPermission('view')->orHasPermission('edit')->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasPermission('edit')->orHasPermission('delete')->check())->toBeFalse();
        });
    });

    describe('chained role checks', function () {
        it('hasRole starts a chain', function () {
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole($role);

            expect(Mandate::for($this->user)->hasRole('admin')->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasRole('editor')->check())->toBeFalse();
        });

        it('andHasRole requires both roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRole('admin');
            $this->user->assignRole('editor');

            expect(Mandate::for($this->user)->hasRole('admin')->andHasRole('editor')->check())->toBeTrue();

            $this->user->removeRole('editor');

            expect(Mandate::for($this->user)->hasRole('admin')->andHasRole('editor')->check())->toBeFalse();
        });

        it('orHasRole requires either role', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRole('admin');

            expect(Mandate::for($this->user)->hasRole('admin')->orHasRole('editor')->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasRole('editor')->orHasRole('moderator')->check())->toBeFalse();
        });
    });

    describe('mixed role and permission checks', function () {
        it('hasRole orHasPermission works', function () {
            $permission = Permission::create(['name' => 'edit-articles', 'guard' => 'web']);
            $this->user->grantPermission($permission);

            // User has permission but not role - should pass due to OR
            expect(Mandate::for($this->user)->hasRole('admin')->orHasPermission('edit-articles')->check())->toBeTrue();
        });

        it('hasPermission andHasRole requires both', function () {
            $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->grantPermission($permission);
            $this->user->assignRole($role);

            expect(Mandate::for($this->user)->hasPermission('edit')->andHasRole('editor')->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasPermission('edit')->andHasRole('admin')->check())->toBeFalse();
        });
    });

    describe('any checks', function () {
        it('hasAnyPermission checks array of permissions', function () {
            Permission::create(['name' => 'view', 'guard' => 'web']);
            $this->user->grantPermission('view');

            expect(Mandate::for($this->user)->hasAnyPermission(['view', 'edit', 'delete'])->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasAnyPermission(['edit', 'delete'])->check())->toBeFalse();
        });

        it('hasAnyRole checks array of roles', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('admin');

            expect(Mandate::for($this->user)->hasAnyRole(['admin', 'editor', 'moderator'])->check())->toBeTrue();
            expect(Mandate::for($this->user)->hasAnyRole(['editor', 'moderator'])->check())->toBeFalse();
        });
    });

    describe('helper methods', function () {
        it('allowed() is alias for check()', function () {
            $permission = Permission::create(['name' => 'view', 'guard' => 'web']);
            $this->user->grantPermission($permission);

            expect(Mandate::for($this->user)->hasPermission('view')->allowed())->toBeTrue();
        });

        it('denied() returns inverse of check()', function () {
            Permission::create(['name' => 'view', 'guard' => 'web']);

            expect(Mandate::for($this->user)->hasPermission('view')->denied())->toBeTrue();

            $this->user->grantPermission('view');

            expect(Mandate::for($this->user)->hasPermission('view')->denied())->toBeFalse();
        });

        it('returns false for empty conditions', function () {
            expect(Mandate::for($this->user)->check())->toBeFalse();
        });
    });

    describe('with context', function () {
        it('inContext() scopes checks to context', function () {
            $this->enableContext();

            $permission = Permission::create(['name' => 'manage', 'guard' => 'web']);
            $team = Team::create(['name' => 'Team A']);
            $otherTeam = Team::create(['name' => 'Team B']);

            $this->user->grantPermission($permission, $team);

            expect(Mandate::for($this->user)->inContext($team)->hasPermission('manage')->check())->toBeTrue();
            expect(Mandate::for($this->user)->inContext($otherTeam)->hasPermission('manage')->check())->toBeFalse();
        });
    });
});
