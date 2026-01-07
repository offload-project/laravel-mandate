<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\MandateServiceProvider;
use OffloadProject\Mandate\Tests\Fixtures\Feature;
use OffloadProject\Mandate\Tests\Fixtures\MockFeatureAccessHandler;
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

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->runMandateMigrations();
    }

    protected function runMandateMigrations(): void
    {
        $migrationPath = __DIR__.'/../database/migrations';

        $migration = include $migrationPath.'/2024_01_01_000001_create_mandate_tables.php';
        $migration->up();
    }

    protected function runCapabilityMigrations(): void
    {
        // Only run if capabilities table doesn't exist (idempotent)
        if (Schema::hasTable(config('mandate.tables.capabilities', 'capabilities'))) {
            return;
        }

        $migrationPath = __DIR__.'/../database/migrations';

        $migration = include $migrationPath.'/2024_01_01_000002_create_capability_tables.php';
        $migration->up();
    }

    protected function enableEvents(): void
    {
        config(['mandate.events' => true]);
    }

    protected function enableWildcards(): void
    {
        config(['mandate.wildcards.enabled' => true]);
    }

    protected function enableCapabilities(): void
    {
        config(['mandate.capabilities.enabled' => true]);
        $this->runCapabilityMigrations();
    }

    protected function enableDirectCapabilityAssignment(): void
    {
        config(['mandate.capabilities.direct_assignment' => true]);
    }

    protected function enableContext(): void
    {
        config(['mandate.context.enabled' => true]);
        $this->recreateTables();
    }

    protected function enableContextWithoutGlobalFallback(): void
    {
        config(['mandate.context.enabled' => true]);
        config(['mandate.context.global_fallback' => false]);
        $this->recreateTables();
    }

    protected function enableUuids(): void
    {
        config(['mandate.model_id_type' => 'uuid']);
        $this->recreateTables();
    }

    protected function enableUlids(): void
    {
        config(['mandate.model_id_type' => 'ulid']);
        $this->recreateTables();
    }

    /**
     * Drop and recreate all mandate tables with current config.
     */
    protected function recreateTables(): void
    {
        $migrationPath = __DIR__.'/../database/migrations';

        // Drop all mandate tables
        $migration = include $migrationPath.'/2024_01_01_000001_create_mandate_tables.php';
        $migration->down();

        // Recreate with current config
        $migration = include $migrationPath.'/2024_01_01_000001_create_mandate_tables.php';
        $migration->up();
    }

    /**
     * Enable feature integration with a mock handler.
     */
    protected function enableFeatureIntegration(): MockFeatureAccessHandler
    {
        // Feature integration requires context
        $this->enableContext();

        // Create the features table if it doesn't exist
        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        // Enable feature integration
        config(['mandate.features.enabled' => true]);
        config(['mandate.features.models' => [Feature::class]]);

        // Bind and return the mock handler
        $handler = new MockFeatureAccessHandler;
        $this->app->instance(FeatureAccessHandler::class, $handler);

        return $handler;
    }

    /**
     * Set the behavior when feature handler is missing.
     */
    protected function setFeatureMissingHandlerBehavior(string $behavior): void
    {
        config(['mandate.features.on_missing_handler' => $behavior]);
    }
}
