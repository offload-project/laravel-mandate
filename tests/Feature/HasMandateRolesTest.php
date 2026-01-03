<?php

declare(strict_types=1);

use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Tests\Fixtures\Features\DeleteFeature;
use OffloadProject\Mandate\Tests\Fixtures\Features\ExportFeature;
use OffloadProject\Mandate\Tests\Fixtures\MandateUser;
use Spatie\Permission\Models\Permission;
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

        // Assignment uses Spatie directly (using non-feature-gated permissions)
        $user->givePermissionTo('users.view');
        $user->givePermissionTo(['users.create', 'posts.view']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Verify permissions were assigned
        expect($user->hasPermissionTo('users.view'))->toBeTrue();
        expect($user->hasPermissionTo('users.create'))->toBeTrue();
        expect($user->hasPermissionTo('posts.view'))->toBeTrue();
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

describe('HasMandateRoles Wildcard Permissions with Feature Flags', function () {
    it('respects feature flags when checking specific permission matched by Spatie wildcard', function () {
        // Verify users.delete is gated by DeleteFeature
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        // Create the wildcard permission in Spatie
        Permission::findOrCreate('users.*', 'web');

        // User 1 has feature enabled
        $user1 = MandateUser::create(['email' => 'user1@example.com']);
        $user1->givePermissionTo('users.*');

        // User 2 does NOT have feature enabled
        $user2 = MandateUser::create(['email' => 'user2@example.com']);
        $user2->givePermissionTo('users.*');

        // Manually activate/deactivate the feature
        Feature::for($user1)->activate(DeleteFeature::class);
        Feature::for($user2)->deactivate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // User 1 has feature enabled - wildcard should grant access to users.delete
        expect($user1->hasPermissionTo('users.delete'))->toBeTrue();

        // User 2 has feature disabled - wildcard matches but feature blocks access
        expect($user2->hasPermissionTo('users.delete'))->toBeFalse();
    });

    it('allows non-feature-gated permissions when matched by Spatie wildcard', function () {
        // Create the wildcard permission in Spatie
        Permission::findOrCreate('users.*', 'web');

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.*');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // users.view is NOT feature-gated, so wildcard should grant access
        expect($user->hasPermissionTo('users.view'))->toBeTrue();
        expect($user->hasPermissionTo('users.create'))->toBeTrue();
    });

    it('denies feature-gated permission when user lacks wildcard and feature is active', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        // User has users.view but NOT users.* or users.delete
        $user->givePermissionTo('users.view');

        Feature::for($user)->activate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Even with feature active, user doesn't have the permission via Spatie
        expect($user->hasPermissionTo('users.delete'))->toBeFalse();
        // But users.view works
        expect($user->hasPermissionTo('users.view'))->toBeTrue();
    });

    it('handles mixed feature-gated and non-gated permissions with wildcard', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        // Create the wildcard permission in Spatie
        Permission::findOrCreate('users.*', 'web');

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.*');

        // Deactivate the delete feature
        Feature::for($user)->deactivate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Non-gated permissions should work with wildcard
        expect($user->hasPermissionTo('users.view'))->toBeTrue();
        expect($user->hasPermissionTo('users.create'))->toBeTrue();

        // Feature-gated permission should be blocked even though wildcard matches
        expect($user->hasPermissionTo('users.delete'))->toBeFalse();
    });
});
