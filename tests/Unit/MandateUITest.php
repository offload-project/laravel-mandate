<?php

declare(strict_types=1);

use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Support\MandateUI;
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

describe('MandateUI', function () {
    test('auth returns permissions, roles, and features arrays', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');
        $user->assignRole('admin');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $ui = new MandateUI();
        $auth = $ui->auth($user);

        expect($auth)->toHaveKeys(['permissions', 'roles', 'features']);
        expect($auth['permissions'])->toBeArray();
        expect($auth['roles'])->toBeArray();
        expect($auth['features'])->toBeArray();
    });

    test('getPermissions returns permission names', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant(['users.view', 'users.create']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $ui = new MandateUI();
        $permissions = $ui->getPermissions($user);

        expect($permissions)->toContain('users.view');
        expect($permissions)->toContain('users.create');
    });

    test('getRoles returns role names', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->assignRole(['admin', 'editor']);

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $ui = new MandateUI();
        $roles = $ui->getRoles($user);

        expect($roles)->toContain('admin');
        expect($roles)->toContain('editor');
    });

    test('getFeatures returns empty array when no features', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $ui = new MandateUI();
        $features = $ui->getFeatures($user);

        expect($features)->toBeArray();
    });

    test('permissionsMap returns map of permission names to boolean', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();
        $user = $user->fresh();

        $ui = new MandateUI();
        $map = $ui->permissionsMap($user);

        expect($map)->toBeArray();
        expect($map['users.view'])->toBeTrue();
    });

    test('grouped returns grouped permissions, roles, and features', function () {
        $ui = new MandateUI();
        $grouped = $ui->grouped();

        expect($grouped)->toHaveKeys(['permissions', 'roles', 'features']);
        expect($grouped['permissions'])->toBeArray();
        expect($grouped['roles'])->toBeArray();
        expect($grouped['features'])->toBeArray();
    });

    test('getPermissions returns empty array for model without trait', function () {
        $model = new class extends Illuminate\Database\Eloquent\Model {};

        $ui = new MandateUI();
        $permissions = $ui->getPermissions($model);

        expect($permissions)->toBe([]);
    });

    test('getRoles returns empty array for model without trait', function () {
        $model = new class extends Illuminate\Database\Eloquent\Model {};

        $ui = new MandateUI();
        $roles = $ui->getRoles($model);

        expect($roles)->toBe([]);
    });
});
