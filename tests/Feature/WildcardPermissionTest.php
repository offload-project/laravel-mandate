<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OffloadProject\Mandate\Http\Middleware\MandatePermission;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Clear caches
    app(MandateManager::class)->clearCache();
});

describe('Wildcard Config Expansion', function () {
    it('expands prefix wildcard to matching permissions', function () {
        $mandate = app(MandateManager::class);

        // Configure admin role with a wildcard permission for users.*
        config()->set('mandate.role_permissions', [
            'admin' => ['users.*'],
        ]);

        // Sync permissions first
        $mandate->syncPermissions();

        // Clear cache and sync roles
        $mandate->clearCache();
        $mandate->syncRoles(seed: true);

        // Get the role and check its permissions
        $role = Role::findByName('admin', 'web');

        // Should have all users.* permissions (view, create, delete)
        expect($role->permissions->pluck('name')->toArray())
            ->toContain('users.view')
            ->toContain('users.create')
            ->toContain('users.delete')
            ->not->toContain('posts.view')
            ->not->toContain('reports.view');
    });

    it('expands suffix wildcard to matching permissions', function () {
        $mandate = app(MandateManager::class);

        // Configure viewer role with a wildcard permission for *.view
        config()->set('mandate.role_permissions', [
            'viewer' => ['*.view'],
        ]);

        // Sync permissions first
        $mandate->syncPermissions();

        // Clear cache and sync roles
        $mandate->clearCache();
        $mandate->syncRoles(seed: true);

        // Get the role and check its permissions
        $role = Role::findByName('viewer', 'web');

        // Should have all *.view permissions
        expect($role->permissions->pluck('name')->toArray())
            ->toContain('users.view')
            ->toContain('posts.view')
            ->toContain('reports.view')
            ->not->toContain('users.create')
            ->not->toContain('users.delete')
            ->not->toContain('posts.create');
    });

    it('combines wildcard and explicit permissions', function () {
        $mandate = app(MandateManager::class);

        // Configure editor role with both wildcard and explicit permissions
        config()->set('mandate.role_permissions', [
            'editor' => [
                '*.view',              // All view permissions
                'posts.create',        // Plus explicit posts.create
            ],
        ]);

        // Sync
        $mandate->syncPermissions();
        $mandate->clearCache();
        $mandate->syncRoles(seed: true);

        $role = Role::findByName('editor', 'web');
        $permissions = $role->permissions->pluck('name')->toArray();

        expect($permissions)
            ->toContain('users.view')
            ->toContain('posts.view')
            ->toContain('reports.view')
            ->toContain('posts.create')
            ->not->toContain('users.create');
    });
});

describe('Wildcard Permission Checks', function () {
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

        // Sync permissions
        app(MandateManager::class)->syncPermissions();
    });

    it('returns true when user has any matching permission for prefix wildcard', function () {
        $mandate = app(MandateManager::class);

        // Create user using TestUser class
        $user = TestUser::create(['email' => 'test@example.com']);

        // Assign only users.view permission
        $permission = Permission::findByName('users.view', 'web');
        $user->givePermissionTo($permission);

        // Clear Spatie cache
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Check wildcard - should match because user has users.view
        expect($mandate->can($user, 'users.*'))->toBeTrue();

        // But not posts.*
        expect($mandate->can($user, 'posts.*'))->toBeFalse();
    });

    it('returns true when user has any matching permission for suffix wildcard', function () {
        $mandate = app(MandateManager::class);

        // Create user
        $user = TestUser::create(['email' => 'test@example.com']);

        // Assign users.view and posts.view permissions
        $user->givePermissionTo(['users.view', 'posts.view']);

        // Clear Spatie cache
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Check wildcard - should match
        expect($mandate->can($user, '*.view'))->toBeTrue();

        // But not *.delete
        expect($mandate->can($user, '*.delete'))->toBeFalse();
    });

    it('returns false when user has no matching permissions', function () {
        $mandate = app(MandateManager::class);

        // Create user with posts permissions only
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo(['posts.view', 'posts.create']);

        // Clear Spatie cache
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // users.* should not match
        expect($mandate->can($user, 'users.*'))->toBeFalse();
    });

    it('handles exact match when not using wildcard', function () {
        $mandate = app(MandateManager::class);

        // Create user
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        // Clear Spatie cache
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Exact match should work
        expect($mandate->can($user, 'users.view'))->toBeTrue();
        expect($mandate->can($user, 'users.create'))->toBeFalse();
    });
});

describe('Wildcard Middleware', function () {
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

        // Sync permissions
        app(MandateManager::class)->syncPermissions();
    });

    it('allows access when user has matching wildcard permission', function () {
        // Create user with users.view permission
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        // Clear Spatie cache
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Create request with authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new MandatePermission;
        $response = $middleware->handle($request, fn () => response('OK'), 'users.*');

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user lacks matching wildcard permission', function () {
        // Create user with posts permissions only
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('posts.view');

        // Clear Spatie cache
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Create request with authenticated user
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new MandatePermission;

        expect(fn () => $middleware->handle($request, fn () => response('OK'), 'users.*'))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('generates correct middleware string for wildcard pattern', function () {
        expect(MandatePermission::using('users.*'))
            ->toBe('mandate.permission:users.*');

        expect(MandatePermission::using('*.view'))
            ->toBe('mandate.permission:*.view');
    });
});
