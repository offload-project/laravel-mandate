<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Class Directories
    |--------------------------------------------------------------------------
    |
    | These directories will be scanned for permission classes. Classes must
    | use the #[PermissionsSet] attribute.
    |
    | Example permission class:
    |
    | #[PermissionsSet('users')]
    | final class UserPermissions
    | {
    |     #[Label('View Users')]
    |     public const VIEW = 'view users';
    |
    |     #[Label('Create Users')]
    |     public const CREATE = 'create users';
    | }
    |
    */

    'permission_directories' => [
        app_path('Permissions') => 'App\\Permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Class Directories
    |--------------------------------------------------------------------------
    |
    | These directories will be scanned for role classes. Classes must
    | use the #[RoleSet] attribute.
    |
    | Example role class:
    |
    | #[RoleSet('system')]
    | final class SystemRoles
    | {
    |     #[Label('Administrator')]
    |     public const string ADMINISTRATOR = 'administrator';
    |
    |     #[Label('User')]
    |     public const string USER = 'user';
    | }
    |
    */

    'role_directories' => [
        app_path('Roles') => 'App\\Roles',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Permissions Mapping
    |--------------------------------------------------------------------------
    |
    | Map roles to their permissions. Supports:
    | - Permission classes (all constants)
    | - String permission names
    |
    | IMPORTANT: These mappings are only applied when:
    | 1. A role is first created (during any sync)
    | 2. Running `php artisan mandate:sync --seed`
    |
    | By default, `mandate:sync` will NOT overwrite role-permission
    | relationships in the database. This allows you to manage permissions
    | via UI/database without config overwriting your changes.
    |
    | Use `--seed` flag for initial setup or when you intentionally want
    | to reset role permissions to match this config.
    |
    | Example:
    |
    | use App\Permissions\UserPermissions;
    | use App\Permissions\PostPermissions;
    | use App\Roles\SystemRoles;
    |
    | 'role_permissions' => [
    |     SystemRoles::ADMINISTRATOR => [
    |         UserPermissions::class,    // All user permissions
    |         PostPermissions::class,    // All post permissions
    |     ],
    |
    |     SystemRoles::EDITOR => [
    |         PostPermissions::VIEW,
    |         PostPermissions::CREATE,
    |         PostPermissions::UPDATE,
    |     ],
    |
    |     'viewer' => [                  // String role name also works
    |         'view posts',
    |         'view users',
    |     ],
    | ],
    |
    */

    'role_permissions' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Additional Columns
    |--------------------------------------------------------------------------
    |
    | Configure which additional columns to sync to the permissions and roles
    | tables. By default, only 'name' and 'guard_name' are synced (required
    | by Spatie Permission).
    |
    | Available columns:
    | - 'set': The set name from #[PermissionsSet] or #[RoleSet]
    | - 'label': The label from #[Label] attribute
    | - 'description': The description from #[Description] attribute
    |
    | To use these columns, you must add them to your permissions/roles tables.
    | Publish the migration to add 'set' column:
    |   php artisan vendor:publish --tag=mandate-migrations
    |
    | For 'label' and 'description', add them manually or modify the migration.
    |
    | Set to true to sync all columns, or an array of specific columns:
    | - true: Sync all columns (set, label, description)
    | - ['set', 'label']: Only sync set and label
    | - false: Only sync name and guard_name (default)
    |
    */

    'sync_columns' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto Sync
    |--------------------------------------------------------------------------
    |
    | When enabled, permissions and roles will be automatically synced
    | to the database when the service provider boots. Disable in production
    | and use the artisan command instead.
    |
    */

    'auto_sync' => env('MANDATE_AUTO_SYNC', false),

    /*
    |--------------------------------------------------------------------------
    | TypeScript Export Path
    |--------------------------------------------------------------------------
    |
    | The path where the TypeScript permissions/roles file will be generated
    | when running the mandate:typescript command. Set to null to require
    | the --output option to be specified.
    |
    */

    'typescript_path' => resource_path('js/permissions.ts'),

    /*
    |--------------------------------------------------------------------------
    | Gate Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, Mandate registers a Gate::before() hook that routes
    | Laravel's authorization checks through Mandate for permissions and features.
    |
    | Permission checks (with feature flag awareness):
    |   $user->can('users.view')       // Checks permission + feature flag
    |   Gate::allows('users.view')
    |   @can('users.view')             // Blade
    |   ->middleware('can:users.view')
    |
    | Feature checks (by name or class):
    |   $user->can('export')           // By feature name
    |   $user->can(ExportFeature::class)
    |   @can('export')                 // Blade
    |
    | When disabled, use Mandate::can() directly for feature-aware checks.
    |
    */

    'gate_integration' => env('MANDATE_GATE_INTEGRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    |
    | Enable Spatie's wildcard permission feature. When enabled, you can assign
    | wildcard permissions like 'users.*' that match specific permissions like
    | 'users.view' or 'users.create'.
    |
    | Example:
    |   $user->grantPermission('users.*');
    |   $user->holdsPermission('users.view');  // true
    |   $user->holdsPermission('users.create'); // true
    |
    | See: https://spatie.be/docs/laravel-permission/v6/basic-usage/wildcard-permissions
    |
    */

    'wildcard_permissions' => env('MANDATE_WILDCARD_PERMISSIONS', false),

];
