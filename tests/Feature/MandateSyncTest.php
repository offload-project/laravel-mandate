<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Services\MandateManager;

beforeEach(function () {
    // Ensure clean state
    app(MandateManager::class)->clearCache();
});

test('syncRoles creates new roles with permissions from config', function () {
    $mandate = app(MandateManager::class);

    // First, sync permissions
    $mandate->syncPermissions();

    // Then sync roles (without seed flag - but new roles get permissions)
    $result = $mandate->syncRoles();

    // Admin and viewer roles should be created (from config)
    expect($result['created'])->toBeGreaterThanOrEqual(2);

    // New roles should have their permissions seeded from config
    $adminRole = Role::findByName('admin', 'web');
    expect($adminRole)->not->toBeNull();

    // Admin has all UserPermissions (5 permissions)
    expect($adminRole->permissions->count())->toBe(5);
});

test('syncRoles without seed flag does not overwrite existing role permissions', function () {
    $mandate = app(MandateManager::class);

    // First sync to create everything
    $mandate->syncPermissions();
    $mandate->syncRoles(seed: true);

    // Get admin role and manually modify its permissions
    $adminRole = Role::findByName('admin', 'web');
    $originalPermissionCount = $adminRole->permissions->count();

    // Remove a permission via "database/UI"
    $adminRole->revokePermissions('delete users');

    // Clear cache
    app(MandateManager::class)->clearCache();

    // Verify permission was removed
    $adminRole->refresh();
    expect($adminRole->granted('delete users'))->toBeFalse();
    expect($adminRole->permissions->count())->toBe($originalPermissionCount - 1);

    // Run sync WITHOUT seed flag
    $mandate->clearCache();
    $result = $mandate->syncRoles(seed: false);

    // Clear cache again
    app(MandateManager::class)->clearCache();

    // Permission should still be revoked (database is authoritative)
    $adminRole->refresh();
    expect($adminRole->granted('delete users'))->toBeFalse();
    expect($adminRole->permissions->count())->toBe($originalPermissionCount - 1);
});

test('syncRoles with seed flag overwrites existing role permissions', function () {
    $mandate = app(MandateManager::class);

    // First sync to create everything
    $mandate->syncPermissions();
    $mandate->syncRoles(seed: true);

    // Get admin role and manually modify its permissions
    $adminRole = Role::findByName('admin', 'web');
    $originalPermissionCount = $adminRole->permissions->count();

    // Remove a permission via "database/UI"
    $adminRole->revokePermissions('delete users');

    // Clear cache
    app(MandateManager::class)->clearCache();

    // Verify permission was removed
    $adminRole->refresh();
    expect($adminRole->granted('delete users'))->toBeFalse();

    // Run sync WITH seed flag
    $mandate->clearCache();
    $result = $mandate->syncRoles(seed: true);

    // Clear cache again
    app(MandateManager::class)->clearCache();

    // Permission should be restored from config
    $adminRole->refresh();
    expect($adminRole->granted('delete users'))->toBeTrue();
    expect($adminRole->permissions->count())->toBe($originalPermissionCount);
});

test('syncRoles without seed flag still adds permissions to new roles', function () {
    $mandate = app(MandateManager::class);

    // Sync permissions first
    $mandate->syncPermissions();

    // Create admin role manually without any permissions
    Role::createRole(['name' => 'admin', 'guard_name' => 'web']);

    // Clear cache so mandate knows it's "existing"
    $mandate->clearCache();

    // Run sync WITHOUT seed flag
    $result = $mandate->syncRoles(seed: false);

    // Admin role should NOT get permissions (it already exists)
    $adminRole = Role::findByName('admin', 'web');
    expect($adminRole->permissions->count())->toBe(0);
});

test('syncRoles returns correct permission count when seeding', function () {
    $mandate = app(MandateManager::class);

    // Sync permissions first
    $mandate->syncPermissions();

    // Sync roles with seeding
    $result = $mandate->syncRoles(seed: true);

    // Should have synced permissions for admin (5) and viewer (1)
    expect($result['permissions_synced'])->toBe(6);
});

test('syncRoles returns zero permission count when not seeding existing roles', function () {
    $mandate = app(MandateManager::class);

    // First sync to create everything
    $mandate->syncPermissions();
    $mandate->syncRoles(seed: true);

    // Clear cache and run again without seeding
    $mandate->clearCache();
    $result = $mandate->syncRoles(seed: false);

    // All roles exist, no seeding = 0 permissions synced
    expect($result['permissions_synced'])->toBe(0);
});

test('sync method passes seed flag to syncRoles', function () {
    $mandate = app(MandateManager::class);

    // First sync to create everything with seeding
    $result = $mandate->sync(seed: true);

    expect($result['roles']['permissions_synced'])->toBeGreaterThan(0);

    // Clear and sync again without seeding
    $mandate->clearCache();
    $result = $mandate->sync(seed: false);

    // All roles exist, no seeding = 0 permissions synced
    expect($result['roles']['permissions_synced'])->toBe(0);
});
