<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('ShowCommand', function () {
    it('shows empty state when no roles or permissions exist', function () {
        $this->artisan('mandate:show')
            ->expectsOutputToContain('No roles found')
            ->expectsOutputToContain('No permissions found')
            ->assertSuccessful();
    });

    it('shows roles', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'editor', 'guard' => 'web']);

        $this->artisan('mandate:show')
            ->expectsOutputToContain('admin')
            ->expectsOutputToContain('editor')
            ->assertSuccessful();
    });

    it('shows permissions', function () {
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        $this->artisan('mandate:show')
            ->expectsOutputToContain('article:view')
            ->expectsOutputToContain('article:edit')
            ->assertSuccessful();
    });

    it('shows roles with their permissions', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $role->grantPermission($permission);

        $this->artisan('mandate:show')
            ->expectsOutputToContain('editor')
            ->expectsOutputToContain('article:edit')
            ->assertSuccessful();
    });

    it('filters by guard', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'api-admin', 'guard' => 'api']);

        $this->artisan('mandate:show', ['--guard' => 'web'])
            ->expectsOutputToContain('admin')
            ->assertSuccessful();
    });
});
