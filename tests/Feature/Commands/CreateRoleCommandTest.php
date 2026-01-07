<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('CreateRoleCommand', function () {
    it('creates a role', function () {
        $this->artisan('mandate:role', [
            'name' => 'admin',
        ])
            ->assertSuccessful();

        expect(Role::where('name', 'admin')->exists())->toBeTrue();
    });

    it('creates a role with specified guard', function () {
        $this->artisan('mandate:role', [
            'name' => 'admin',
            '--guard' => 'api',
        ])
            ->assertSuccessful();

        expect(Role::where('name', 'admin')->where('guard', 'api')->exists())->toBeTrue();
    });

    it('creates a role with permissions', function () {
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        $this->artisan('mandate:role', [
            'name' => 'editor',
            '--permissions' => 'article:view,article:edit',
        ])
            ->assertSuccessful();

        $role = Role::where('name', 'editor')->first();

        expect($role->permissions)->toHaveCount(2);
    });

    it('warns when role already exists', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $this->artisan('mandate:role', [
            'name' => 'admin',
        ])
            ->assertSuccessful();
    });
});
