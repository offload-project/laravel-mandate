<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use OffloadProject\Hoist\Services\FeatureDiscovery;
use OffloadProject\Mandate\Console\Commands\MandateSyncCommand;
use OffloadProject\Mandate\Console\Commands\PermissionMakeCommand;
use OffloadProject\Mandate\Console\Commands\RoleMakeCommand;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Http\Middleware\MandateFeature;
use OffloadProject\Mandate\Http\Middleware\MandatePermission;
use OffloadProject\Mandate\Http\Middleware\MandateRole;
use OffloadProject\Mandate\Services\DatabaseSyncer;
use OffloadProject\Mandate\Services\FeatureRegistry;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Services\PermissionRegistry;
use OffloadProject\Mandate\Services\RoleRegistry;
use Spatie\Permission\PermissionRegistrar;

final class MandateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mandate.php', 'mandate');

        $this->registerFeatureRegistry();
        $this->registerPermissionRegistry();
        $this->registerRoleRegistry();
        $this->registerDatabaseSyncer();
        $this->registerMandateManager();
    }

    public function boot(): void
    {
        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MandateSyncCommand::class,
                PermissionMakeCommand::class,
                RoleMakeCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/mandate.php' => config_path('mandate.php'),
            ], 'mandate-config');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/mandate'),
            ], 'mandate-stubs');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'mandate-migrations');
        }

        // Auto sync if enabled
        if (config('mandate.auto_sync', false)) {
            $this->app->booted(function () {
                $this->app->make(MandateManager::class)->sync();
            });
        }
    }

    /**
     * Register the feature registry.
     */
    private function registerFeatureRegistry(): void
    {
        $this->app->singleton(FeatureRegistry::class, function ($app) {
            return new FeatureRegistry(
                $app->make(FeatureDiscovery::class),
            );
        });

        $this->app->alias(FeatureRegistry::class, FeatureRegistryContract::class);
    }

    /**
     * Register the permission registry.
     */
    private function registerPermissionRegistry(): void
    {
        $this->app->singleton(PermissionRegistry::class, function ($app) {
            return new PermissionRegistry(
                $app->make(FeatureRegistryContract::class),
            );
        });

        $this->app->alias(PermissionRegistry::class, PermissionRegistryContract::class);
    }

    /**
     * Register the role registry.
     */
    private function registerRoleRegistry(): void
    {
        $this->app->singleton(RoleRegistry::class, function ($app) {
            return new RoleRegistry(
                $app->make(FeatureRegistryContract::class),
            );
        });

        $this->app->alias(RoleRegistry::class, RoleRegistryContract::class);
    }

    /**
     * Register the database syncer.
     */
    private function registerDatabaseSyncer(): void
    {
        $this->app->singleton(DatabaseSyncer::class, function ($app) {
            return new DatabaseSyncer(
                $app->make(PermissionRegistrar::class),
            );
        });
    }

    /**
     * Register the mandate manager.
     */
    private function registerMandateManager(): void
    {
        $this->app->singleton(MandateManager::class, function ($app) {
            return new MandateManager(
                $app->make(FeatureRegistryContract::class),
                $app->make(PermissionRegistryContract::class),
                $app->make(RoleRegistryContract::class),
                $app->make(DatabaseSyncer::class),
            );
        });
    }

    /**
     * Register the middleware aliases.
     */
    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('mandate.permission', MandatePermission::class);
        $router->aliasMiddleware('mandate.role', MandateRole::class);
        $router->aliasMiddleware('mandate.feature', MandateFeature::class);
    }
}
