<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('mandate:role --db', function () {
    it('creates a role in database', function () {
        $this->artisan('mandate:role', [
            'name' => 'admin',
            '--db' => true,
        ])
            ->assertSuccessful();

        expect(Role::where('name', 'admin')->exists())->toBeTrue();
    });

    it('creates a role with specified guard', function () {
        $this->artisan('mandate:role', [
            'name' => 'admin',
            '--guard' => 'api',
            '--db' => true,
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
            '--db' => true,
        ])
            ->assertSuccessful();

        $role = Role::where('name', 'editor')->first();

        expect($role->permissions)->toHaveCount(2);
    });

    it('warns when role already exists', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $this->artisan('mandate:role', [
            'name' => 'admin',
            '--db' => true,
        ])
            ->assertSuccessful();
    });
});
