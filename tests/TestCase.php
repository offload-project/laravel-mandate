<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\MandateServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use InteractsWithViews;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            MandateServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
        $app['config']->set('auth.guards.web.provider', 'users');

        $app['config']->set('mandate.events', false);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        $this->runMandateMigrations();
    }

    protected function runMandateMigrations(): void
    {
        $migrationPath = __DIR__.'/../database/migrations';

        $migrationFiles = [
            '2024_01_01_000001_create_permissions_table.php',
            '2024_01_01_000002_create_roles_table.php',
            '2024_01_01_000003_create_permission_role_table.php',
            '2024_01_01_000004_create_permission_subject_table.php',
            '2024_01_01_000005_create_role_subject_table.php',
        ];

        foreach ($migrationFiles as $file) {
            $migration = include $migrationPath.'/'.$file;
            $migration->up();
        }
    }

    protected function enableEvents(): void
    {
        config(['mandate.events' => true]);
    }

    protected function enableWildcards(): void
    {
        config(['mandate.wildcards.enabled' => true]);
    }
}
