<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $app['config']->set('database.connections.testing', $this->testDatabaseConfig());

        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
        $app['config']->set('auth.guards.web.provider', 'users');

        $app['config']->set('mandate.events', false);

        // Use array cache driver for tests to avoid needing a cache table
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Build the connection config for the driver under test.
     *
     * Defaults to in-memory SQLite. CI also runs the suite against Postgres and MySQL,
     * which enforce constraints SQLite does not - notably NOT NULL on key columns.
     *
     * @return array<string, mixed>
     */
    protected function testDatabaseConfig(): array
    {
        return match (env('DB_CONNECTION', 'sqlite')) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'mandate'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'mandate'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        };
    }

    /**
     * Determine whether the suite is running against a persistent database.
     */
    protected function usingPersistentDatabase(): bool
    {
        return env('DB_CONNECTION', 'sqlite') !== 'sqlite';
    }

    protected function setUpDatabase(): void
    {
        // A persistent database keeps the previous test's tables, and the suite relies on
        // building its schema from scratch in every test.
        if ($this->usingPersistentDatabase()) {
            Schema::dropAllTables();
        }

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

    /**
     * Get the column type name the current driver reports for a Mandate id type.
     *
     * Every driver names its types differently, so schema assertions resolve the
     * expected name here instead of hard coding SQLite's.
     */
    protected function expectedColumnType(string $idType): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($idType) {
            'uuid' => match ($driver) {
                'pgsql' => 'uuid',
                'mysql', 'mariadb' => 'char',
                default => 'varchar',
            },
            'ulid' => match ($driver) {
                'mysql', 'mariadb' => 'char',
                default => 'varchar',
            },
            default => match ($driver) {
                'pgsql' => 'int8',
                'mysql', 'mariadb' => 'bigint',
                default => 'integer',
            },
        };
    }

    /**
     * Insert a pivot row that points at a parent that does not exist.
     *
     * Postgres and MySQL enforce the foreign key, so it is dropped first. The schema is
     * rebuilt for the next test either way.
     *
     * @param  array<string, mixed>  $row
     */
    protected function insertOrphanedPivot(string $table, string $foreignKey, array $row): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
            $blueprint->dropForeign([$foreignKey]);
        });

        DB::table($table)->insert($row);
    }
}
