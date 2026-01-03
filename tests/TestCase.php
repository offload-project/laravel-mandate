<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OffloadProject\Hoist\HoistServiceProvider;
use OffloadProject\Mandate\MandateServiceProvider;
use OffloadProject\Mandate\Tests\Fixtures\Permissions\UserPermissions;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure hoist to use test fixtures for features
        config()->set('hoist.feature_directories', [
            __DIR__ . '/Fixtures/Features' => 'OffloadProject\\Mandate\\Tests\\Fixtures\\Features',
        ]);

        // Configure mandate to use test fixtures
        config()->set('mandate.permission_directories', [
            __DIR__ . '/Fixtures/Permissions' => 'OffloadProject\\Mandate\\Tests\\Fixtures\\Permissions',
        ]);

        config()->set('mandate.role_directories', [
            __DIR__ . '/Fixtures/Roles' => 'OffloadProject\\Mandate\\Tests\\Fixtures\\Roles',
        ]);

        // Configure role permissions mapping
        config()->set('mandate.role_permissions', [
            'admin' => [
                UserPermissions::class,
            ],
            'viewer' => [
                UserPermissions::VIEW,
            ],
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            HoistServiceProvider::class,
            MandateServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup auth guard
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => User::class,
        ]);

        // Enable Spatie wildcard permissions
        $app['config']->set('permission.enable_wildcard_permission', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Run Spatie permission migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
