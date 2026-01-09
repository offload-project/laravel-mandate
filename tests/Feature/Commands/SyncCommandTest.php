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
