<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\PennantServiceProvider;
use OffloadProject\Hoist\HoistServiceProvider;
use OffloadProject\Mandate\MandateServiceProvider;
use OffloadProject\Mandate\Tests\Fixtures\Permissions\UserPermissions;
use Orchestra\Testbench\TestCase as BaseTestCase;

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
        config()->set('mandate.discovery.permissions', [
            __DIR__ . '/Fixtures/Permissions' => 'OffloadProject\\Mandate\\Tests\\Fixtures\\Permissions',
        ]);

        config()->set('mandate.discovery.roles', [
            __DIR__ . '/Fixtures/Roles' => 'OffloadProject\\Mandate\\Tests\\Fixtures\\Roles',
        ]);

        // Configure role permissions mapping
        config()->set('mandate-seed.role_permissions', [
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
            PennantServiceProvider::class,
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

        // Enable wildcard permissions
        $app['config']->set('mandate.wildcards', true);

        // Configure Pennant to use array driver (in-memory for tests)
        $app['config']->set('pennant.default', 'array');
        $app['config']->set('pennant.stores.array', ['driver' => 'array']);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load the package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
