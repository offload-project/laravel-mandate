<?php

declare(strict_types=1);

use OffloadProject\Mandate\Events\MandateSynced;
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('SyncCommand', function () {
    beforeEach(function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../../Fixtures/CodeFirst']);

        // Reset static caches between tests
        Permission::resetLabelColumnCache();
        Role::resetLabelColumnCache();
    });

    it('fails when code-first is disabled', function () {
        config(['mandate.code_first.enabled' => false]);

        $this->artisan('mandate:sync')
            ->expectsOutputToContain('Code-first mode is not enabled')
            ->assertFailed();
    });

    it('syncs permissions to database', function () {
        $this->artisan('mandate:sync', ['--permissions' => true])
            ->assertSuccessful();

        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:create')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:edit')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:delete')->exists())->toBeTrue();
    });

    it('syncs roles to database', function () {
        $this->artisan('mandate:sync', ['--roles' => true])
            ->assertSuccessful();

        expect(Role::where('name', 'admin')->exists())->toBeTrue();
        expect(Role::where('name', 'editor')->exists())->toBeTrue();
        expect(Role::where('name', 'viewer')->exists())->toBeTrue();
    });

    it('updates existing records with new labels', function () {
        // Run label column migration first
        $migrationPath = __DIR__.'/../../../database/migrations';
        $migration = include $migrationPath.'/2024_01_01_000003_add_label_description_to_mandate_tables.php';
        $migration->up();

        // Reset the static cache
        Permission::resetLabelColumnCache();

        // Create a permission without label
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        $this->artisan('mandate:sync', ['--permissions' => true])
            ->assertSuccessful();

        $permission = Permission::where('name', 'article:view')->first();
        expect($permission->label)->toBe('View Articles');
    });

    it('supports dry-run mode', function () {
        $this->artisan('mandate:sync', ['--permissions' => true, '--dry-run' => true])
            ->expectsOutputToContain('Dry run mode')
            ->assertSuccessful();

        // Nothing should be created
        expect(Permission::count())->toBe(0);
    });

    it('supports guard filter', function () {
        $this->artisan('mandate:sync', ['--permissions' => true, '--guard' => 'api'])
            ->assertSuccessful();

        // No permissions should match 'api' guard in fixtures
        expect(Permission::count())->toBe(0);
    });

    it('dispatches sync events', function () {
        Event::fake([PermissionsSynced::class, RolesSynced::class, MandateSynced::class]);

        $this->artisan('mandate:sync')
            ->assertSuccessful();

        Event::assertDispatched(PermissionsSynced::class);
        Event::assertDispatched(RolesSynced::class);
        Event::assertDispatched(MandateSynced::class);
    });
})->skip(fn () => ! class_exists(OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer::class), 'Code-first not implemented');

describe('SyncCommand --seed', function () {
    it('works without code-first enabled', function () {
        config(['mandate.code_first.enabled' => false]);

        // Create role and permission in database
        $role = Role::create(['name' => 'admin', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'user:manage', 'guard' => 'web']);

        // Configure assignments
        config(['mandate.assignments' => [
            'admin' => [
                'permissions' => ['user:manage'],
            ],
        ]]);

        $this->artisan('mandate:sync', ['--seed' => true])
            ->assertSuccessful();

        $role->refresh();
        expect($role->hasPermission('user:manage'))->toBeTrue();
    });

    it('seeds role-permission assignments from config', function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../../Fixtures/CodeFirst']);

        // Create role and permission
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        // Configure assignments
        config(['mandate.assignments' => [
            'editor' => [
                'permissions' => ['article:view', 'article:edit'],
            ],
        ]]);

        $this->artisan('mandate:sync', ['--seed' => true])
            ->assertSuccessful();

        $role->refresh();
        expect($role->hasPermission('article:view'))->toBeTrue();
        expect($role->hasPermission('article:edit'))->toBeTrue();
    });

    it('warns when role not found during seeding', function () {
        config(['mandate.code_first.enabled' => false]);

        // Configure assignments for non-existent role
        config(['mandate.assignments' => [
            'nonexistent' => [
                'permissions' => ['some:permission'],
            ],
        ]]);

        $this->artisan('mandate:sync', ['--seed' => true])
            ->expectsOutputToContain("Role 'nonexistent' not found")
            ->assertSuccessful();
    });

    it('reads assignments from top-level config not code_first', function () {
        config(['mandate.code_first.enabled' => false]);

        // Create role and permission
        $role = Role::create(['name' => 'tester', 'guard' => 'web']);
        Permission::create(['name' => 'test:run', 'guard' => 'web']);

        // Set assignments at wrong location (old location) - should NOT work
        config(['mandate.code_first.assignments' => [
            'tester' => [
                'permissions' => ['test:run'],
            ],
        ]]);

        // Ensure top-level assignments is empty
        config(['mandate.assignments' => []]);

        $this->artisan('mandate:sync', ['--seed' => true])
            ->assertSuccessful();

        $role->refresh();
        // Should NOT have the permission since we used wrong config location
        expect($role->hasPermission('test:run'))->toBeFalse();
    });
});
