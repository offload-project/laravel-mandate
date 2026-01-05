<?php

declare(strict_types=1);

use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Support\ModelScope;
use OffloadProject\Mandate\Tests\Fixtures\MandateUser;

beforeEach(function () {
    config()->set('pennant.default', 'array');
    config()->set('pennant.stores.array', ['driver' => 'array']);

    $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    app(MandateManager::class)->clearCache();
    app(MandateManager::class)->syncPermissions();
    app(MandateManager::class)->syncRoles();
});

describe('ModelScope', function () {
    test('grantPermission grants permission to model', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        $scope->grantPermission('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeTrue();
    });

    test('grantPermission returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        $result = $scope->grantPermission('users.view');

        expect($result)->toBe($scope);
    });

    test('revokePermission revokes permission from model', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $scope = new ModelScope($user);
        $scope->revokePermission('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->granted('users.view'))->toBeFalse();
    });

    test('assignRole assigns role to model', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        $scope->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedRole('admin'))->toBeTrue();
    });

    test('unassignRole removes role from model', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $scope = new ModelScope($user);
        $scope->unassignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        expect($user->assignedRole('admin'))->toBeFalse();
    });

    test('granted checks if model has permission', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $scope = new ModelScope($user);

        expect($scope->granted('users.view'))->toBeTrue();
        expect($scope->granted('users.delete'))->toBeFalse();
    });

    test('assignedRole checks if model has role', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole('editor');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $scope = new ModelScope($user);

        expect($scope->assignedRole('editor'))->toBeTrue();
        expect($scope->assignedRole('admin'))->toBeFalse();
    });

    test('getModel returns the underlying model', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        expect($scope->getModel())->toBe($user);
    });

    test('enableFeature returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        $result = $scope->enableFeature('test-feature');

        expect($result)->toBe($scope);
    });

    test('disableFeature returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        $result = $scope->disableFeature('test-feature');

        expect($result)->toBe($scope);
    });

    test('forgetFeature returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        $result = $scope->forgetFeature('test-feature');

        expect($result)->toBe($scope);
    });

    test('hasAccess returns false for inactive feature', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $scope = new ModelScope($user);

        // Default state should be inactive
        expect($scope->hasAccess('non-existent-feature'))->toBeFalse();
    });

    test('methods handle model without traits gracefully', function () {
        $model = new class extends Illuminate\Database\Eloquent\Model
        {
            protected $guarded = [];
        };

        $scope = new ModelScope($model);

        // Should not throw errors
        $scope->grantPermission('test');
        $scope->revokePermission('test');
        $scope->assignRole('test');
        $scope->unassignRole('test');

        expect($scope->granted('test'))->toBeFalse();
        expect($scope->assignedRole('test'))->toBeFalse();
        expect($scope->hasAccess('test'))->toBeFalse();
    });
});
