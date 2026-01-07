<?php

declare(strict_types=1);

use OffloadProject\Mandate\Facades\Mandate;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\Team;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
    $this->team = Team::create(['name' => 'Test Team']);
    $this->otherTeam = Team::create(['name' => 'Other Team']);
});

describe('Context Model Feature', function () {
    describe('permissions with context', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('can grant permission with context', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage', $this->team);

            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue();
        });

        it('can grant permission without context (global)', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage');

            expect($this->user->hasPermission('project:manage'))->toBeTrue();
        });

        it('can grant same permission to multiple contexts', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage', $this->team);
            $this->user->grantPermission('project:manage', $this->otherTeam);

            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue()
                ->and($this->user->hasPermission('project:manage', $this->otherTeam))->toBeTrue();
        });

        it('does not have permission for different context without global fallback', function () {
            // Disable global fallback for this specific test
            config(['mandate.context.global_fallback' => false]);
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage', $this->team);

            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue()
                ->and($this->user->hasPermission('project:manage', $this->otherTeam))->toBeFalse();
        });

        it('can revoke permission with context', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $this->user->grantPermission('project:manage', $this->team);

            $this->user->revokePermission('project:manage', $this->team);

            expect($this->user->hasPermission('project:manage', $this->team))->toBeFalse();
        });

        it('revoke with context does not affect global permission', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $this->user->grantPermission('project:manage'); // global
            $this->user->grantPermission('project:manage', $this->team);

            $this->user->revokePermission('project:manage', $this->team);

            expect($this->user->hasPermission('project:manage'))->toBeTrue();
        });

        it('revoke global does not affect context permission', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $this->user->grantPermission('project:manage'); // global
            $this->user->grantPermission('project:manage', $this->team);

            $this->user->revokePermission('project:manage'); // revoke global

            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue();
        });

        it('can sync permissions with context', function () {
            Permission::create(['name' => 'project:view', 'guard' => 'web']);
            Permission::create(['name' => 'project:edit', 'guard' => 'web']);
            Permission::create(['name' => 'project:delete', 'guard' => 'web']);

            $this->user->grantPermission('project:view', $this->team);
            $this->user->grantPermission('project:edit', $this->team);

            $this->user->syncPermissions(['project:delete'], $this->team);

            expect($this->user->hasPermission('project:view', $this->team))->toBeFalse()
                ->and($this->user->hasPermission('project:edit', $this->team))->toBeFalse()
                ->and($this->user->hasPermission('project:delete', $this->team))->toBeTrue();
        });

        it('sync with context does not affect other contexts', function () {
            Permission::create(['name' => 'project:view', 'guard' => 'web']);
            Permission::create(['name' => 'project:edit', 'guard' => 'web']);

            $this->user->grantPermission('project:view', $this->team);
            $this->user->grantPermission('project:view', $this->otherTeam);

            $this->user->syncPermissions(['project:edit'], $this->team);

            expect($this->user->hasPermission('project:view', $this->team))->toBeFalse()
                ->and($this->user->hasPermission('project:edit', $this->team))->toBeTrue()
                ->and($this->user->hasPermission('project:view', $this->otherTeam))->toBeTrue();
        });
    });

    describe('global fallback for permissions', function () {
        it('checks global permission when context is provided and global_fallback enabled', function () {
            $this->enableContext();
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage'); // global

            // Should have permission for any context when global fallback enabled
            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue();
        });

        it('does not check global permission when global_fallback disabled', function () {
            $this->enableContextWithoutGlobalFallback();
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage'); // global

            // Should NOT have permission for context when fallback disabled
            expect($this->user->hasPermission('project:manage', $this->team))->toBeFalse()
                ->and($this->user->hasPermission('project:manage'))->toBeTrue(); // but global still works
        });
    });

    describe('roles with context', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('can assign role with context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);

            $this->user->assignRole('manager', $this->team);

            expect($this->user->hasRole('manager', $this->team))->toBeTrue();
        });

        it('can assign role without context (global)', function () {
            Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->assignRole('admin');

            expect($this->user->hasRole('admin'))->toBeTrue();
        });

        it('can assign same role to multiple contexts', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);

            $this->user->assignRole('manager', $this->team);
            $this->user->assignRole('manager', $this->otherTeam);

            expect($this->user->hasRole('manager', $this->team))->toBeTrue()
                ->and($this->user->hasRole('manager', $this->otherTeam))->toBeTrue();
        });

        it('does not have role for different context without global fallback', function () {
            // Disable global fallback for this specific test
            config(['mandate.context.global_fallback' => false]);
            Role::create(['name' => 'manager', 'guard' => 'web']);

            $this->user->assignRole('manager', $this->team);

            expect($this->user->hasRole('manager', $this->team))->toBeTrue()
                ->and($this->user->hasRole('manager', $this->otherTeam))->toBeFalse();
        });

        it('can remove role with context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);

            $this->user->removeRole('manager', $this->team);

            expect($this->user->hasRole('manager', $this->team))->toBeFalse();
        });

        it('remove with context does not affect global role', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            $this->user->assignRole('manager'); // global
            $this->user->assignRole('manager', $this->team);

            $this->user->removeRole('manager', $this->team);

            expect($this->user->hasRole('manager'))->toBeTrue();
        });

        it('can sync roles with context', function () {
            Role::create(['name' => 'viewer', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            Role::create(['name' => 'manager', 'guard' => 'web']);

            $this->user->assignRole('viewer', $this->team);
            $this->user->assignRole('editor', $this->team);

            $this->user->syncRoles(['manager'], $this->team);

            expect($this->user->hasRole('viewer', $this->team))->toBeFalse()
                ->and($this->user->hasRole('editor', $this->team))->toBeFalse()
                ->and($this->user->hasRole('manager', $this->team))->toBeTrue();
        });
    });

    describe('global fallback for roles', function () {
        it('checks global role when context is provided and global_fallback enabled', function () {
            $this->enableContext();
            Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->assignRole('admin'); // global

            // Should have role for any context when global fallback enabled
            expect($this->user->hasRole('admin', $this->team))->toBeTrue();
        });

        it('does not check global role when global_fallback disabled', function () {
            $this->enableContextWithoutGlobalFallback();
            Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->assignRole('admin'); // global

            // Should NOT have role for context when fallback disabled
            expect($this->user->hasRole('admin', $this->team))->toBeFalse()
                ->and($this->user->hasRole('admin'))->toBeTrue(); // but global still works
        });
    });

    describe('permissions via roles with context', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('gets permission via role with context', function () {
            $role = Role::create(['name' => 'manager', 'guard' => 'web']);
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $role->grantPermission('project:manage');

            $this->user->assignRole('manager', $this->team);

            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue()
                ->and($this->user->hasPermissionViaRole('project:manage', $this->team))->toBeTrue();
        });

        it('does not get permission via role for different context without global fallback', function () {
            // Disable global fallback for this specific test
            config(['mandate.context.global_fallback' => false]);
            $role = Role::create(['name' => 'manager', 'guard' => 'web']);
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $role->grantPermission('project:manage');

            $this->user->assignRole('manager', $this->team);

            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue()
                ->and($this->user->hasPermission('project:manage', $this->otherTeam))->toBeFalse();
        });
    });

    describe('getting contexts', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('can get all contexts where user has a permission', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $this->user->grantPermission('project:manage', $this->team);
            $this->user->grantPermission('project:manage', $this->otherTeam);

            $contexts = $this->user->getPermissionContexts('project:manage');

            expect($contexts)->toHaveCount(2)
                ->and($contexts->pluck('id')->toArray())->toContain($this->team->id, $this->otherTeam->id);
        });

        it('can get all contexts where user has a role', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);
            $this->user->assignRole('manager', $this->otherTeam);

            $contexts = $this->user->getRoleContexts('manager');

            expect($contexts)->toHaveCount(2)
                ->and($contexts->pluck('id')->toArray())->toContain($this->team->id, $this->otherTeam->id);
        });

        it('returns empty collection when no contexts', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $this->user->grantPermission('project:manage'); // global only

            $contexts = $this->user->getPermissionContexts('project:manage');

            expect($contexts)->toHaveCount(0);
        });
    });

    describe('getting roles for context', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('can get roles for specific context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);
            $this->user->assignRole('admin'); // global

            $roles = $this->user->getRolesForContext($this->team);

            // With global fallback enabled, should get both
            expect($roles)->toHaveCount(2);
        });

        it('can get roles for context without global fallback', function () {
            // Disable global fallback for this specific test
            config(['mandate.context.global_fallback' => false]);
            Role::create(['name' => 'manager', 'guard' => 'web']);
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);
            $this->user->assignRole('admin'); // global

            $roles = $this->user->getRolesForContext($this->team);

            // Without global fallback, should only get context-specific role
            expect($roles)->toHaveCount(1)
                ->and($roles->first()->name)->toBe('manager');
        });
    });

    describe('Mandate facade with context', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('can check permission via facade with context', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            $this->user->grantPermission('project:manage', $this->team);

            expect(Mandate::hasPermission($this->user, 'project:manage', $this->team))->toBeTrue();
        });

        it('can check role via facade with context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);

            expect(Mandate::hasRole($this->user, 'manager', $this->team))->toBeTrue();
        });

        it('can get permissions via facade with context', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            Permission::create(['name' => 'project:view', 'guard' => 'web']);
            $this->user->grantPermission('project:manage', $this->team);
            $this->user->grantPermission('project:view'); // global

            $permissions = Mandate::getPermissions($this->user, $this->team);

            // With global fallback, should get both
            expect($permissions)->toHaveCount(2);
        });

        it('can get role names via facade with context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);

            $roleNames = Mandate::getRoleNames($this->user, $this->team);

            expect($roleNames)->toContain('manager');
        });

        it('returns context enabled status', function () {
            expect(Mandate::contextEnabled())->toBeTrue();
        });
    });

    describe('hasAny and hasAll with context', function () {
        beforeEach(function () {
            $this->enableContext();
        });

        it('hasAnyPermission works with context', function () {
            Permission::create(['name' => 'project:view', 'guard' => 'web']);
            Permission::create(['name' => 'project:edit', 'guard' => 'web']);
            $this->user->grantPermission('project:view', $this->team);

            expect($this->user->hasAnyPermission(['project:view', 'project:edit'], $this->team))->toBeTrue()
                ->and($this->user->hasAnyPermission(['project:delete'], $this->team))->toBeFalse();
        });

        it('hasAllPermissions works with context', function () {
            Permission::create(['name' => 'project:view', 'guard' => 'web']);
            Permission::create(['name' => 'project:edit', 'guard' => 'web']);
            $this->user->grantPermission('project:view', $this->team);
            $this->user->grantPermission('project:edit', $this->team);

            expect($this->user->hasAllPermissions(['project:view', 'project:edit'], $this->team))->toBeTrue()
                ->and($this->user->hasAllPermissions(['project:view', 'project:delete'], $this->team))->toBeFalse();
        });

        it('hasAnyRole works with context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            Role::create(['name' => 'admin', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);

            expect($this->user->hasAnyRole(['manager', 'admin'], $this->team))->toBeTrue()
                ->and($this->user->hasAnyRole(['viewer'], $this->team))->toBeFalse();
        });

        it('hasAllRoles works with context', function () {
            Role::create(['name' => 'manager', 'guard' => 'web']);
            Role::create(['name' => 'editor', 'guard' => 'web']);
            $this->user->assignRole('manager', $this->team);
            $this->user->assignRole('editor', $this->team);

            expect($this->user->hasAllRoles(['manager', 'editor'], $this->team))->toBeTrue()
                ->and($this->user->hasAllRoles(['manager', 'admin'], $this->team))->toBeFalse();
        });
    });

    describe('context disabled behavior', function () {
        it('works normally without context enabled', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);
            Role::create(['name' => 'admin', 'guard' => 'web']);

            $this->user->grantPermission('project:manage');
            $this->user->assignRole('admin');

            expect($this->user->hasPermission('project:manage'))->toBeTrue()
                ->and($this->user->hasRole('admin'))->toBeTrue()
                ->and(Mandate::contextEnabled())->toBeFalse();
        });

        it('ignores context parameter when context disabled', function () {
            Permission::create(['name' => 'project:manage', 'guard' => 'web']);

            $this->user->grantPermission('project:manage');

            // Context parameter is ignored when feature is disabled
            expect($this->user->hasPermission('project:manage', $this->team))->toBeTrue();
        });
    });
});
