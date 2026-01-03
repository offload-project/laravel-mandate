<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;

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
});

describe('Gate Integration Disabled', function () {
    beforeEach(function () {
        config()->set('mandate.gate_integration', false);
    });

    it('does not intercept Gate checks when disabled', function () {
        // Sync permissions
        app(MandateManager::class)->syncPermissions();

        // Create user with permission
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Gate check should go through Spatie directly (returns true)
        // Since gate_integration is disabled, Mandate's Gate::before is not registered
        expect(Gate::forUser($user)->allows('users.view'))->toBeTrue();
    });
});

describe('Gate Integration Enabled', function () {
    beforeEach(function () {
        config()->set('mandate.gate_integration', true);

        // Re-register the Gate integration since config changed after boot
        Gate::before(function ($user, $ability) {
            $permissionRegistry = app(OffloadProject\Mandate\Contracts\PermissionRegistryContract::class);

            if ($permissionRegistry->find($ability) !== null) {
                return app(MandateManager::class)->can($user, $ability);
            }

            return null;
        });
    });

    it('allows access when user has permission', function () {
        // Sync permissions
        app(MandateManager::class)->syncPermissions();

        // Create user with permission
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // Gate check should work through Mandate
        expect(Gate::forUser($user)->allows('users.view'))->toBeTrue();
    });

    it('denies access when user lacks permission', function () {
        // Sync permissions
        app(MandateManager::class)->syncPermissions();

        // Create user without permission
        $user = TestUser::create(['email' => 'test@example.com']);

        // Gate check should deny
        expect(Gate::forUser($user)->allows('users.view'))->toBeFalse();
    });

    it('works with $user->can() method', function () {
        // Sync permissions
        app(MandateManager::class)->syncPermissions();

        // Create user with permission
        $user = TestUser::create(['email' => 'test@example.com']);
        $user->givePermissionTo('users.view');

        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        // $user->can() should work
        expect($user->can('users.view'))->toBeTrue();
        expect($user->can('users.delete'))->toBeFalse();
    });

    it('falls through for non-Mandate permissions', function () {
        // Define a custom Gate ability that's not in Mandate
        Gate::define('custom-ability', fn ($user) => true);

        $user = TestUser::create(['email' => 'test@example.com']);

        // Should fall through to the custom Gate definition
        expect(Gate::forUser($user)->allows('custom-ability'))->toBeTrue();
    });
});
