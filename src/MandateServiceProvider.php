<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use OffloadProject\Hoist\Services\FeatureDiscovery;
use OffloadProject\Mandate\Console\Commands\FeatureMakeCommand;
use OffloadProject\Mandate\Console\Commands\MandateSyncCommand;
use OffloadProject\Mandate\Console\Commands\PermissionMakeCommand;
use OffloadProject\Mandate\Console\Commands\RoleMakeCommand;
use OffloadProject\Mandate\Console\Commands\TypescriptGenerateCommand;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Http\Middleware\MandateFeature;
use OffloadProject\Mandate\Http\Middleware\MandateInertiaAuthShare;
use OffloadProject\Mandate\Http\Middleware\MandatePermission;
use OffloadProject\Mandate\Http\Middleware\MandateRole;
use OffloadProject\Mandate\Services\DatabaseSyncer;
use OffloadProject\Mandate\Services\FeatureRegistry;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Services\PermissionRegistry;
use OffloadProject\Mandate\Services\RoleHierarchyResolver;
use OffloadProject\Mandate\Services\RoleRegistry;
use OffloadProject\Mandate\Support\MandateUI;

final class MandateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mandate.php', 'mandate');
        $this->mergeConfigFrom(__DIR__.'/../config/mandate-seed.php', 'mandate-seed');

        $this->configureHoist();
        $this->registerFeatureRegistry();
        $this->registerPermissionRegistry();
        $this->registerRoleRegistry();
        $this->registerDatabaseSyncer();
        $this->registerMandateManager();
        $this->registerMandateUI();
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerGateIntegration();

        if ($this->app->runningInConsole()) {
            $this->commands([
                FeatureMakeCommand::class,
                MandateSyncCommand::class,
                PermissionMakeCommand::class,
                RoleMakeCommand::class,
                TypescriptGenerateCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/mandate.php' => config_path('mandate.php'),
            ], 'mandate-config');

            $this->publishes([
                __DIR__.'/../config/mandate-seed.php' => config_path('mandate-seed.php'),
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
     * Configure Hoist feature discovery directories.
     */
    private function configureHoist(): void
    {
        // Set Hoist feature directories to match Mandate's discovery config
        $featureDirectories = config('mandate.discovery.features', []);
        if (! empty($featureDirectories)) {
            config()->set('hoist.feature_directories', $featureDirectories);
        }
    }

    /**
     * Register the feature registry.
     */
    private function registerFeatureRegistry(): void
    {
        $this->app->singleton(FeatureRegistry::class, function ($app) {
            // Only create with FeatureDiscovery if Hoist is available
            if (class_exists(FeatureDiscovery::class)) {
                return new FeatureRegistry(
                    $app->make(FeatureDiscovery::class),
                );
            }

            // Return a null registry if Hoist is not available
            return new class implements FeatureRegistryContract
            {
                public function all(): Collection
                {
                    return collect();
                }

                public function forModel(Model $model): Collection
                {
                    return collect();
                }

                public function find(string $class): ?Data\FeatureData
                {
                    return null;
                }

                public function permissions(string $class): Collection
                {
                    return collect();
                }

                public function roles(string $class): Collection
                {
                    return collect();
                }

                public function isActive(Model $model, string $class): bool
                {
                    return false;
                }

                public function clearCache(): void {}
            };
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
     * Register the role hierarchy resolver.
     */
    private function registerRoleHierarchyResolver(): void
    {
        $this->app->singleton(RoleHierarchyResolver::class);
    }

    /**
     * Register the role registry.
     */
    private function registerRoleRegistry(): void
    {
        $this->registerRoleHierarchyResolver();

        $this->app->singleton(RoleRegistry::class, function ($app) {
            return new RoleRegistry(
                $app->make(FeatureRegistryContract::class),
                $app->make(RoleHierarchyResolver::class),
            );
        });

        $this->app->alias(RoleRegistry::class, RoleRegistryContract::class);
    }

    /**
     * Register the database syncer.
     */
    private function registerDatabaseSyncer(): void
    {
        $this->app->singleton(DatabaseSyncer::class);
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
     * Register the MandateUI service.
     */
    private function registerMandateUI(): void
    {
        $this->app->singleton(MandateUI::class);
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
        $router->aliasMiddleware('mandate.inertia', MandateInertiaAuthShare::class);
    }

    /**
     * Register the Gate integration for Laravel's authorization.
     *
     * When enabled, this routes Laravel's can/Gate checks through Mandate's
     * feature-aware permission system and also checks features.
     */
    private function registerGateIntegration(): void
    {
        if (! config('mandate.gate_integration', false)) {
            return;
        }

        Gate::before(function ($user, $ability) {
            // Mandate requires an Eloquent model for permission/feature checks
            if (! $user instanceof Model) {
                return null;
            }

            $permissionRegistry = $this->app->make(PermissionRegistryContract::class);
            $featureRegistry = $this->app->make(FeatureRegistryContract::class);

            // Check if this is a Mandate-managed permission
            if ($permissionRegistry->find($ability) !== null) {
                return $this->app->make(MandateManager::class)->can($user, $ability);
            }

            // Check if this is a feature (by class name)
            if ($featureRegistry->find($ability) !== null) {
                return $featureRegistry->isActive($user, $ability);
            }

            // Check if this is a feature (by name)
            $featureByName = $featureRegistry->all()->firstWhere('name', $ability);
            if ($featureByName !== null) {
                return $featureRegistry->isActive($user, $featureByName->class);
            }

            // Not a Mandate permission or feature - let other Gate handlers deal with it
            return null;
        });
    }
}
