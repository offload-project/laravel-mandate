<?php

declare(strict_types=1);

use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Tests\Fixtures\Features\DeleteFeature;
use OffloadProject\Mandate\Tests\Fixtures\Features\ExportFeature;
use OffloadProject\Mandate\Tests\Fixtures\MandateUser;

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
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeTrue();
        expect($user->granted('users.delete'))->toBeFalse();
    });

    it('respects feature flags for permissions', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $exportPermission = $permissionRegistry->find('export users');

        if ($exportPermission === null || $exportPermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        // Verify the permission has the correct feature class
        expect($exportPermission->feature)->toBe(ExportFeature::class);

        $user1 = MandateUser::create(['email' => 'user1@example.com']);
        $user1->grant('export users');

        $user2 = MandateUser::create(['email' => 'user2@example.com']);
        $user2->grant('export users');

        Feature::for($user1)->activate(ExportFeature::class);
        Feature::for($user2)->deactivate(ExportFeature::class);

        // Verify Pennant state directly
        expect(Feature::for($user1)->active(ExportFeature::class))->toBeTrue();
        expect(Feature::for($user2)->active(ExportFeature::class))->toBeFalse();

        app(MandateManager::class)->clearCache();
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // User 1 has feature enabled - should have permission
        expect($user1->granted('export users'))->toBeTrue();

        // User 2 has feature disabled - should NOT have permission
        expect($user2->granted('export users'))->toBeFalse();
    });
});

describe('HasMandateRoles Role Checks', function () {
    beforeEach(function () {
        app(MandateManager::class)->syncRoles();
    });

    it('checks non-feature-gated roles normally', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedRole('admin'))->toBeTrue();
        expect($user->assignedRole('viewer'))->toBeFalse();
    });

    it('checks assignedAnyRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('editor');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedAnyRole(['admin', 'editor']))->toBeTrue();
        expect($user->assignedAnyRole(['admin', 'viewer']))->toBeFalse();
    });

    it('checks assignedAllRoles', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole(['admin', 'editor']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedAllRoles(['admin', 'editor']))->toBeTrue();
        expect($user->assignedAllRoles(['admin', 'viewer']))->toBeFalse();
    });

    it('returns false for assignedAllRoles with empty input', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        // Empty array should return false (not vacuous truth)
        expect($user->assignedAllRoles([]))->toBeFalse();
    });

    it('handles Role model objects in assignedRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $role = Role::findByName('admin', 'web');
        expect($user->assignedRole($role))->toBeTrue();
    });
});

describe('HasMandateRoles Assignment Methods', function () {
    beforeEach(function () {
        app(MandateManager::class)->syncRoles();
    });

    it('grants permissions with grant', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $user->grant('users.view');
        $user->grant(['users.create', 'posts.view']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeTrue();
        expect($user->granted('users.create'))->toBeTrue();
        expect($user->granted('posts.view'))->toBeTrue();
    });

    it('grants roles with assignRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $user->assignRole('admin');
        $user->assignRole(['editor']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedRole('admin'))->toBeTrue();
        expect($user->assignedRole('editor'))->toBeTrue();
    });

    it('revokes permissions with revoke', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeTrue();

        $user->revoke('users.view');
        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeFalse();
    });

    it('revokes roles with unassignRole', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedRole('admin'))->toBeTrue();

        $user->unassignRole('admin');
        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedRole('admin'))->toBeFalse();
    });
});

describe('HasMandateRoles grantedAnyPermission and grantedAllPermissions', function () {
    it('returns false for grantedAllPermissions with empty input', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        // Empty array should return false (not vacuous truth)
        expect($user->grantedAllPermissions([]))->toBeFalse();
        expect($user->grantedAllPermissions())->toBeFalse();
    });

    it('checks grantedAnyPermission', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant(['users.view', 'users.create']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->grantedAnyPermission(['users.view', 'posts.view']))->toBeTrue();
        expect($user->grantedAnyPermission(['users.view', 'posts.view']))->toBeTrue();
        expect($user->grantedAnyPermission(['posts.view', 'reports.view']))->toBeFalse();
    });

    it('checks grantedAllPermissions', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant(['users.view', 'users.create', 'posts.view']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->grantedAllPermissions(['users.view', 'users.create']))->toBeTrue();
        expect($user->grantedAllPermissions(['users.view', 'posts.view']))->toBeTrue();
        expect($user->grantedAllPermissions(['users.view', 'reports.view']))->toBeFalse();
    });

    it('respects feature flags in grantedAnyPermission', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant(['users.delete', 'users.view']);

        Feature::for($user)->deactivate(DeleteFeature::class);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        // users.delete is feature-gated and inactive, but users.view is not
        expect($user->grantedAnyPermission(['users.delete', 'users.view']))->toBeTrue();

        // Only users.delete which is blocked
        expect($user->grantedAnyPermission(['users.delete']))->toBeFalse();
    });

    it('respects feature flags in grantedAllPermissions', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant(['users.delete', 'users.view']);

        Feature::for($user)->deactivate(DeleteFeature::class);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        // users.delete is feature-gated and inactive
        expect($user->grantedAllPermissions(['users.delete', 'users.view']))->toBeFalse();

        // Activate the feature
        Feature::for($user)->activate(DeleteFeature::class);
        expect($user->grantedAllPermissions(['users.delete', 'users.view']))->toBeTrue();
    });
});

describe('HasMandateRoles Wildcard Permissions with Feature Flags', function () {
    it('respects feature flags when checking specific permission matched by wildcard', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        Permission::query()->firstOrCreate(['name' => 'users.*', 'guard_name' => 'web']);

        $user1 = MandateUser::create(['email' => 'user1@example.com']);
        $user1->grant('users.*');

        $user2 = MandateUser::create(['email' => 'user2@example.com']);
        $user2->grant('users.*');

        Feature::for($user1)->activate(DeleteFeature::class);
        Feature::for($user2)->deactivate(DeleteFeature::class);

        app(MandateManager::class)->clearCache();
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // User 1 has feature enabled - wildcard should grant access
        expect($user1->granted('users.delete'))->toBeTrue();

        // User 2 has feature disabled - wildcard matches but feature blocks access
        expect($user2->granted('users.delete'))->toBeFalse();
    });

    it('allows non-feature-gated permissions when matched by wildcard', function () {
        Permission::query()->firstOrCreate(['name' => 'users.*', 'guard_name' => 'web']);

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.*');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeTrue();
        expect($user->granted('users.create'))->toBeTrue();
    });

    it('denies feature-gated permission when user lacks wildcard and feature is active', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        Feature::for($user)->activate(DeleteFeature::class);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        // Even with feature active, user doesn't have the permission
        expect($user->granted('users.delete'))->toBeFalse();
        expect($user->granted('users.view'))->toBeTrue();
    });

    it('handles mixed feature-gated and non-gated permissions with wildcard', function () {
        $permissionRegistry = app(PermissionRegistryContract::class);
        $deletePermission = $permissionRegistry->find('users.delete');

        if ($deletePermission === null || $deletePermission->feature === null) {
            $this->markTestSkipped('Feature discovery not configured for this test environment');
        }

        Permission::query()->firstOrCreate(['name' => 'users.*', 'guard_name' => 'web']);

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.*');

        Feature::for($user)->deactivate(DeleteFeature::class);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        // Non-gated permissions should work with wildcard
        expect($user->granted('users.view'))->toBeTrue();
        expect($user->granted('users.create'))->toBeTrue();

        // Feature-gated permission should be blocked even though wildcard matches
        expect($user->granted('users.delete'))->toBeFalse();
    });
});
