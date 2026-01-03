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
        $user->grantPermission('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsPermission('users.view'))->toBeTrue();
        expect($user->holdsPermission('users.delete'))->toBeFalse();
    });

    it('respects feature flags for permissions', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $exportPermission = $permissionRegistry->find('export users');

        if ($exportPermission === null || $exportPermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user1 = MandateUser::create(['email' => 'user1@example.com']);
        $user1->grantPermission('export users');

        $user2 = MandateUser::create(['email' => 'user2@example.com']);
        $user2->grantPermission('export users');

        Feature::for($user1)->activate(ExportFeature::class);
        Feature::for($user2)->deactivate(ExportFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // User 1 has feature enabled - should have permission
        expect($user1->holdsPermission('export users'))->toBeTrue();

        // User 2 has feature disabled - should NOT have permission
        expect($user2->holdsPermission('export users'))->toBeFalse();
    });
});

describe('HasMandateRoles Role Checks', function () {
    beforeEach(function () {
        app(MandateManager::class)->syncRoles();
    });

    it('checks non-feature-gated roles normally', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsRole('admin'))->toBeTrue();
        expect($user->holdsRole('viewer'))->toBeFalse();
    });

    it('checks holdsAnyRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantRole('editor');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsAnyRole(['admin', 'editor']))->toBeTrue();
        expect($user->holdsAnyRole(['admin', 'viewer']))->toBeFalse();
    });

    it('checks holdsAllRoles', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantRole(['admin', 'editor']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsAllRoles(['admin', 'editor']))->toBeTrue();
        expect($user->holdsAllRoles(['admin', 'viewer']))->toBeFalse();
    });

    it('returns false for holdsAllRoles with empty input', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Empty array should return false (not vacuous truth)
        expect($user->holdsAllRoles([]))->toBeFalse();
    });

    it('handles Role model objects in holdsRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $role = Role::findByName('admin', 'web');
        expect($user->holdsRole($role))->toBeTrue();
    });
});

describe('HasMandateRoles Assignment Methods', function () {
    beforeEach(function () {
        app(MandateManager::class)->syncRoles();
    });

    it('grants permissions with grantPermission', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $user->grantPermission('users.view');
        $user->grantPermission(['users.create', 'posts.view']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsPermission('users.view'))->toBeTrue();
        expect($user->holdsPermission('users.create'))->toBeTrue();
        expect($user->holdsPermission('posts.view'))->toBeTrue();
    });

    it('grants roles with grantRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $user->grantRole('admin');
        $user->grantRole(['editor']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsRole('admin'))->toBeTrue();
        expect($user->holdsRole('editor'))->toBeTrue();
    });

    it('revokes permissions with revokePermission', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsPermission('users.view'))->toBeTrue();

        $user->revokePermission('users.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsPermission('users.view'))->toBeFalse();
    });

    it('revokes roles with revokeRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantRole('admin');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsRole('admin'))->toBeTrue();

        $user->revokeRole('admin');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsRole('admin'))->toBeFalse();
    });
});

describe('HasMandateRoles holdsAnyPermission and holdsAllPermissions', function () {
    it('returns false for holdsAllPermissions with empty input', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Empty array should return false (not vacuous truth)
        expect($user->holdsAllPermissions([]))->toBeFalse();
        expect($user->holdsAllPermissions())->toBeFalse();
    });

    it('checks holdsAnyPermission', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission(['users.view', 'users.create']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsAnyPermission('users.view', 'posts.view'))->toBeTrue();
        expect($user->holdsAnyPermission(['users.view', 'posts.view']))->toBeTrue();
        expect($user->holdsAnyPermission('posts.view', 'reports.view'))->toBeFalse();
    });

    it('checks holdsAllPermissions', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission(['users.view', 'users.create', 'posts.view']);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsAllPermissions('users.view', 'users.create'))->toBeTrue();
        expect($user->holdsAllPermissions(['users.view', 'posts.view']))->toBeTrue();
        expect($user->holdsAllPermissions('users.view', 'reports.view'))->toBeFalse();
    });

    it('respects feature flags in holdsAnyPermission', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission(['users.delete', 'users.view']);

        Feature::for($user)->deactivate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // users.delete is feature-gated and inactive, but users.view is not
        expect($user->holdsAnyPermission('users.delete', 'users.view'))->toBeTrue();

        // Only users.delete which is blocked
        expect($user->holdsAnyPermission('users.delete'))->toBeFalse();
    });

    it('respects feature flags in holdsAllPermissions', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission(['users.delete', 'users.view']);

        Feature::for($user)->deactivate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // users.delete is feature-gated and inactive
        expect($user->holdsAllPermissions('users.delete', 'users.view'))->toBeFalse();

        // Activate the feature
        Feature::for($user)->activate(DeleteFeature::class);
        expect($user->holdsAllPermissions('users.delete', 'users.view'))->toBeTrue();
    });
});

describe('HasMandateRoles Wildcard Permissions with Feature Flags', function () {
    it('respects feature flags when checking specific permission matched by Spatie wildcard', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        Permission::findOrCreate('users.*', 'web');

        $user1 = MandateUser::create(['email' => 'user1@example.com']);
        $user1->grantPermission('users.*');

        $user2 = MandateUser::create(['email' => 'user2@example.com']);
        $user2->grantPermission('users.*');

        Feature::for($user1)->activate(DeleteFeature::class);
        Feature::for($user2)->deactivate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // User 1 has feature enabled - wildcard should grant access
        expect($user1->holdsPermission('users.delete'))->toBeTrue();

        // User 2 has feature disabled - wildcard matches but feature blocks access
        expect($user2->holdsPermission('users.delete'))->toBeFalse();
    });

    it('allows non-feature-gated permissions when matched by Spatie wildcard', function () {
        Permission::findOrCreate('users.*', 'web');

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission('users.*');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        expect($user->holdsPermission('users.view'))->toBeTrue();
        expect($user->holdsPermission('users.create'))->toBeTrue();
    });

    it('denies feature-gated permission when user lacks wildcard and feature is active', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission('users.view');

        Feature::for($user)->activate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Even with feature active, user doesn't have the permission via Spatie
        expect($user->holdsPermission('users.delete'))->toBeFalse();
        expect($user->holdsPermission('users.view'))->toBeTrue();
    });

    it('handles mixed feature-gated and non-gated permissions with wildcard', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        Permission::findOrCreate('users.*', 'web');

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grantPermission('users.*');

        Feature::for($user)->deactivate(DeleteFeature::class);

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Non-gated permissions should work with wildcard
        expect($user->holdsPermission('users.view'))->toBeTrue();
        expect($user->holdsPermission('users.create'))->toBeTrue();

        // Feature-gated permission should be blocked even though wildcard matches
        expect($user->holdsPermission('users.delete'))->toBeFalse();
    });
});
