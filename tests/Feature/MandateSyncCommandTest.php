<?php

declare(strict_types=1);

use OffloadProject\Mandate\Services\MandateManager;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(MandateManager::class)->clearCache();
});

test('mandate:sync command runs without seed flag by default', function () {
    $this->artisan('mandate:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('Role-permission relationships were preserved');
});

test('mandate:sync command with --seed flag seeds permissions', function () {
    $this->artisan('mandate:sync --seed')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Role-permission relationships were preserved');
});

test('mandate:sync command creates roles on first run', function () {
    expect(Role::count())->toBe(0);

    $this->artisan('mandate:sync')
        ->assertSuccessful();

    // Roles should be created
    expect(Role::count())->toBeGreaterThan(0);
    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});

test('mandate:sync --seed syncs permissions to existing roles', function () {
    // First, create roles manually without permissions
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    // Run sync without seed - admin should have no permissions
    $this->artisan('mandate:sync')
        ->assertSuccessful();

    $adminRole = Role::findByName('admin', 'web');
    expect($adminRole->permissions->count())->toBe(0);

    // Now run with --seed
    app(MandateManager::class)->clearCache();
    $this->artisan('mandate:sync --seed')
        ->assertSuccessful();

    // Clear Spatie cache
    app()[Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Admin should now have permissions
    $adminRole->refresh();
    expect($adminRole->permissions->count())->toBeGreaterThan(0);
});

test('mandate:sync --roles only syncs roles', function () {
    // First create permissions
    $this->artisan('mandate:sync --permissions')
        ->assertSuccessful();

    // Then only sync roles
    $this->artisan('mandate:sync --roles --seed')
        ->assertSuccessful();

    expect(Role::count())->toBeGreaterThan(0);
});

test('mandate:sync --permissions only syncs permissions', function () {
    $this->artisan('mandate:sync --permissions')
        ->assertSuccessful();

    // Permissions created, but no roles
    expect(Role::count())->toBe(0);
});
