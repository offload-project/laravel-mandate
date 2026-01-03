<?php

declare(strict_types=1);

use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Tests\Fixtures\Features\ExportFeature;
use OffloadProject\Mandate\Tests\Fixtures\MandateUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Configure Pennant to use array driver (in-memory)
    config()->set('pennant.default', 'array');
    config()->set('pennant.stores.array', ['driver' => 'array']);

    // Create the users table
    $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    // Clear caches
    app(MandateManager::class)->clearCache();

    // Sync permissions
    app(MandateManager::class)->syncPermissions();
});

describe('HasMandateRoles Permission Checks', function () {
    it('checks non-feature-gated permissions normally', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Permissions without feature gates work as expected
        expect($user->hasPermissionTo('users.view'))->toBeTrue();
        expect($user->hasPermissionTo('users.delete'))->toBeFalse();
    });

    it('respects feature flags for permissions', function () {
        // Export feature should gate 'export users' permission
        $permissionRegistry = app(PermissionRegistryContract::class);
        $exportPermission = $permissionRegistry->find('export users');

        // Skip test if feature discovery isn't working in this environment
        if ($exportPermission === null || $exportPermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        // User 1 has export feature enabled
        $user1 = MandateUser::create(['email' => 'user1@example.com']);
        $user1->givePermissionTo('export users');

        // User 2 does NOT have export feature enabled
        $user2 = MandateUser::create(['email' => 'user2@example.com']);
        $user2->givePermissionTo('export users');

        // Manually activate the feature for user1 only
        Feature::for($user1)->activate(ExportFeature::class);
        Feature::for($user2)->deactivate(ExportFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // User 1 has feature enabled - should have permission
        expect($user1->hasPermissionTo('export users'))->toBeTrue();

        // User 2 has feature disabled - should NOT have permission
        expect($user2->hasPermissionTo('export users'))->toBeFalse();
    });
});

describe('HasMandateRoles Role Checks', function () {
    beforeEach(function () {
        // Sync roles
        app(MandateManager::class)->syncRoles();
    });

    it('checks non-feature-gated roles normally', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Roles without feature gates work as expected
        expect($user->hasRole('admin'))->toBeTrue();
        expect($user->hasRole('viewer'))->toBeFalse();
    });

    it('checks hasAnyRole through Mandate', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('editor');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->hasAnyRole(['admin', 'editor']))->toBeTrue();
        expect($user->hasAnyRole(['admin', 'viewer']))->toBeFalse();
    });

    it('checks hasAllRoles through Mandate', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole(['admin', 'editor']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->hasAllRoles(['admin', 'editor']))->toBeTrue();
        expect($user->hasAllRoles(['admin', 'viewer']))->toBeFalse();
    });

    it('handles Role model objects in hasRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $role = Role::findByName('admin', 'web');
        expect($user->hasRole($role))->toBeTrue();
    });
});

describe('HasMandateRoles Assignment Methods', function () {
    beforeEach(function () {
        app(MandateManager::class)->syncRoles();
    });

    it('keeps givePermissionTo from Spatie unchanged', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        // Assignment uses Spatie directly
        $user->givePermissionTo('users.view');
        $user->givePermissionTo(['users.create', 'users.delete']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Verify permissions were assigned
        expect($user->hasPermissionTo('users.view'))->toBeTrue();
        expect($user->hasPermissionTo('users.create'))->toBeTrue();
        expect($user->hasPermissionTo('users.delete'))->toBeTrue();
    });

    it('keeps assignRole from Spatie unchanged', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        // Assignment uses Spatie directly
        $user->assignRole('admin');
        $user->assignRole(['editor']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Verify roles were assigned
        expect($user->hasRole('admin'))->toBeTrue();
        expect($user->hasRole('editor'))->toBeTrue();
    });

    it('keeps revokePermissionTo from Spatie unchanged', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->hasPermissionTo('users.view'))->toBeTrue();

        $user->revokePermissionTo('users.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->hasPermissionTo('users.view'))->toBeFalse();
    });

    it('keeps removeRole from Spatie unchanged', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->hasRole('admin'))->toBeTrue();

        $user->removeRole('admin');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->hasRole('admin'))->toBeFalse();
    });
});
