<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use OffloadProject\Mandate\CodeFirst\DefinitionCache;
use OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer;
use OffloadProject\Mandate\Commands\AssignCapabilityCommand;
use OffloadProject\Mandate\Commands\AssignRoleCommand;
use OffloadProject\Mandate\Commands\ClearCacheCommand;
use OffloadProject\Mandate\Commands\HealthCheckCommand;
use OffloadProject\Mandate\Commands\MakeCapabilityCommand;
use OffloadProject\Mandate\Commands\MakeFeatureCommand;
use OffloadProject\Mandate\Commands\MakePermissionCommand;
use OffloadProject\Mandate\Commands\MakeRoleCommand;
use OffloadProject\Mandate\Commands\ShowCommand;
use OffloadProject\Mandate\Commands\SyncCommand;
use OffloadProject\Mandate\Commands\TypeScriptCommand;
use OffloadProject\Mandate\Commands\UpgradeFromSpatieCommand;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Contracts\WildcardHandler;
use OffloadProject\Mandate\Middleware\PermissionMiddleware;
use OffloadProject\Mandate\Middleware\RoleMiddleware;
use OffloadProject\Mandate\Middleware\RoleOrPermissionMiddleware;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

final class MandateServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mandate.php', 'mandate');

        $this->app->singleton(MandateRegistrar::class);
        $this->app->singleton(Mandate::class);
        $this->app->singleton(DefinitionDiscoverer::class);
        $this->app->singleton(DefinitionCache::class);

        $this->app->bind(PermissionContract::class, fn () => $this->app->make(config('mandate.models.permission', Permission::class)));
        $this->app->bind(RoleContract::class, fn () => $this->app->make(config('mandate.models.role', Role::class)));
        $this->app->bind(CapabilityContract::class, fn () => $this->app->make(config('mandate.models.capability', Capability::class)));
        $this->app->bind(WildcardHandler::class, fn () => $this->app->make(config('mandate.wildcards.handler', WildcardPermission::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslations();
        $this->publishConfig();
        $this->publishMigrations();
        $this->publishTranslations();
        $this->publishStubs();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerBladeDirectives();
        $this->registerRouteMacros();
        $this->registerGate();
    }

    /**
     * Load the package translations.
     */
    private function loadTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'mandate');
    }

    /**
     * Publish the config file.
     */
    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/mandate.php' => config_path('mandate.php'),
        ], 'mandate-config');
    }

    /**
     * Publish the migration files.
     */
    private function publishMigrations(): void
    {
        // Core migrations (permissions, roles, pivot tables)
        $this->publishesMigrations([
            __DIR__.'/../database/migrations/2024_01_01_000001_create_mandate_tables.php' => database_path('migrations/2024_01_01_000001_create_mandate_tables.php'),
        ], 'mandate-migrations');

        // Capability migrations (optional feature)
        $this->publishesMigrations([
            __DIR__.'/../database/migrations/2024_01_01_000002_create_capability_tables.php' => database_path('migrations/2024_01_01_000002_create_capability_tables.php'),
        ], 'mandate-migrations-capabilities');

        // Metadata migrations (label/description columns)
        $this->publishesMigrations([
            __DIR__.'/../database/migrations/2024_01_01_000003_add_label_description_to_mandate_tables.php' => database_path('migrations/2024_01_01_000003_add_label_description_to_mandate_tables.php'),
        ], 'mandate-migrations-meta');
    }

    /**
     * Publish the translation files.
     */
    private function publishTranslations(): void
    {
        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/mandate'),
        ], 'mandate-lang');
    }

    /**
     * Publish the stub files for code-first generators.
     */
    private function publishStubs(): void
    {
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/mandate'),
        ], 'mandate-stubs');
    }

    /**
     * Register the Artisan commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AssignCapabilityCommand::class,
                AssignRoleCommand::class,
                ClearCacheCommand::class,
                ShowCommand::class,
                MakeCapabilityCommand::class,
                MakeFeatureCommand::class,
                MakePermissionCommand::class,
                MakeRoleCommand::class,
                SyncCommand::class,
                TypeScriptCommand::class,
                UpgradeFromSpatieCommand::class,
                HealthCheckCommand::class,
            ]);
        }
    }

    /**
     * Register the middleware aliases.
     */
    private function registerMiddleware(): void
    {
        $router = $this->app->make('router');

        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    /**
     * Register Blade directives.
     */
    private function registerBladeDirectives(): void
    {
        // @role('admin') ... @endrole
        Blade::directive('role', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$expression})): ?>";
        });
        Blade::directive('endrole', function (): string {
            return '<?php endif; ?>';
        });

        // @hasrole('admin') ... @endhasrole (alias for @role)
        Blade::directive('hasrole', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$expression})): ?>";
        });
        Blade::directive('endhasrole', function (): string {
            return '<?php endif; ?>';
        });

        // @unlessrole('admin') ... @endunlessrole
        Blade::directive('unlessrole', function (string $expression): string {
            return "<?php if(!auth()->check() || !auth()->user()->hasRole({$expression})): ?>";
        });
        Blade::directive('endunlessrole', function (): string {
            return '<?php endif; ?>';
        });

        // @hasanyrole('admin|editor') or @hasanyrole(['admin', 'editor'])
        Blade::directive('hasanyrole', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasAnyRole(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasanyrole', function (): string {
            return '<?php endif; ?>';
        });

        // @hasallroles('admin|editor') or @hasallroles(['admin', 'editor'])
        Blade::directive('hasallroles', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasAllRoles(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasallroles', function (): string {
            return '<?php endif; ?>';
        });

        // @hasexactroles('admin|editor') or @hasexactroles(['admin', 'editor'])
        Blade::directive('hasexactroles', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasExactRoles(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasexactroles', function (): string {
            return '<?php endif; ?>';
        });

        // @permission('article:edit') ... @endpermission
        Blade::directive('permission', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasPermission({$expression})): ?>";
        });
        Blade::directive('endpermission', function (): string {
            return '<?php endif; ?>';
        });

        // @unlesspermission('article:edit') ... @endunlesspermission
        Blade::directive('unlesspermission', function (string $expression): string {
            return "<?php if(!auth()->check() || !auth()->user()->hasPermission({$expression})): ?>";
        });
        Blade::directive('endunlesspermission', function (): string {
            return '<?php endif; ?>';
        });

        // @haspermission('article:edit') ... @endhaspermission (alias)
        Blade::directive('haspermission', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasPermission({$expression})): ?>";
        });
        Blade::directive('endhaspermission', function (): string {
            return '<?php endif; ?>';
        });

        // @hasanypermission(['article:edit', 'article:delete'])
        Blade::directive('hasanypermission', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasAnyPermission(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasanypermission', function (): string {
            return '<?php endif; ?>';
        });

        // @hasallpermissions(['article:edit', 'article:delete'])
        Blade::directive('hasallpermissions', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->hasAllPermissions(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasallpermissions', function (): string {
            return '<?php endif; ?>';
        });

        // Capability directives (only work when capabilities are enabled)

        // @capability('manage-posts') ... @endcapability
        Blade::directive('capability', function (string $expression): string {
            return "<?php if(config('mandate.capabilities.enabled', false) && auth()->check() && auth()->user()->hasCapability({$expression})): ?>";
        });
        Blade::directive('endcapability', function (): string {
            return '<?php endif; ?>';
        });

        // @hascapability('manage-posts') ... @endhascapability (alias)
        Blade::directive('hascapability', function (string $expression): string {
            return "<?php if(config('mandate.capabilities.enabled', false) && auth()->check() && auth()->user()->hasCapability({$expression})): ?>";
        });
        Blade::directive('endhascapability', function (): string {
            return '<?php endif; ?>';
        });

        // @hasanycapability(['manage-posts', 'manage-users']) or @hasanycapability('manage-posts|manage-users')
        Blade::directive('hasanycapability', function (string $expression): string {
            return "<?php if(config('mandate.capabilities.enabled', false) && auth()->check() && auth()->user()->hasAnyCapability(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasanycapability', function (): string {
            return '<?php endif; ?>';
        });

        // @hasallcapabilities(['manage-posts', 'manage-users']) or @hasallcapabilities('manage-posts|manage-users')
        Blade::directive('hasallcapabilities', function (string $expression): string {
            return "<?php if(config('mandate.capabilities.enabled', false) && auth()->check() && auth()->user()->hasAllCapabilities(is_array({$expression}) ? {$expression} : explode('|', {$expression}))): ?>";
        });
        Blade::directive('endhasallcapabilities', function (): string {
            return '<?php endif; ?>';
        });
    }

    /**
     * Register route macros for fluent route definition.
     */
    private function registerRouteMacros(): void
    {
        // Route::get('/admin', ...)->permission('admin:access')
        Route::macro('permission', function (string $permission): Route {
            /** @var Route $this */
            return $this->middleware(PermissionMiddleware::using($permission));
        });

        // Route::get('/admin', ...)->role('admin')
        Route::macro('role', function (string $role): Route {
            /** @var Route $this */
            return $this->middleware(RoleMiddleware::using($role));
        });

        // Route::get('/admin', ...)->roleOrPermission('admin|article:manage')
        Route::macro('roleOrPermission', function (string $roleOrPermission): Route {
            /** @var Route $this */
            return $this->middleware(RoleOrPermissionMiddleware::using($roleOrPermission));
        });
    }

    /**
     * Register permissions with Laravel's Gate.
     */
    private function registerGate(): void
    {
        if (! config('mandate.register_gate', true)) {
            return;
        }

        Gate::before(function ($subject, string $ability) {
            if (! $subject instanceof Model || ! method_exists($subject, 'hasPermission')) {
                return null;
            }

            // Check if this is a Mandate permission
            $registrar = app(MandateRegistrar::class);

            // Try to find the permission in cache/database
            if ($registrar->permissionExists($ability, Guard::getNameForModel($subject))) {
                return $subject->hasPermission($ability) ?: null;
            }

            // Not a Mandate permission, let other gates handle it
            return null;
        });
    }
}
