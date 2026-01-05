<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Specify the model classes used by Mandate. You can extend the default
    | models and configure your custom classes here.
    |
    */

    'models' => [
        'role' => OffloadProject\Mandate\Models\Role::class,
        'permission' => OffloadProject\Mandate\Models\Permission::class,
        'feature' => OffloadProject\Mandate\Models\Feature::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | ID Column Type
    |--------------------------------------------------------------------------
    |
    | The type of ID column to use for all Mandate tables. Supports 'bigint'
    | (auto-incrementing) or 'uuid' (UUID strings).
    |
    */

    'id_type' => 'bigint', // 'bigint' | 'uuid'

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Configure the database table names used by Mandate.
    |
    */

    'tables' => [
        'roles' => 'mandate_roles',
        'permissions' => 'mandate_permissions',
        'role_permissions' => 'mandate_role_permissions',
        'subject_roles' => 'mandate_subject_roles',
        'subject_permissions' => 'mandate_subject_permissions',
        'features' => 'mandate_features',
        'subject_features' => 'mandate_subject_features',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    | Configure the pivot table column names.
    |
    */

    'columns' => [
        'pivot_role_key' => 'role_id',
        'pivot_permission_key' => 'permission_id',
        'pivot_feature_key' => 'feature_id',
        'pivot_subject_morph_key' => 'subject',
        'pivot_context_morph_name' => 'context_model',
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Scoping
    |--------------------------------------------------------------------------
    |
    | Enable context columns on specific tables. Context allows scoping
    | roles, permissions, and features to a specific scope (string) or
    | context model (polymorphic relationship).
    |
    | When enabled, context columns are added to the respective tables:
    | - scope: string column for simple scope-based scoping
    | - context_model_type: polymorphic model type
    | - context_model_id: polymorphic model ID
    |
    | Example use cases:
    | - Scope roles to a team: scope='team', contextModel=Team::class
    | - Scope permissions to a tenant: scope='tenant', contextModel=Tenant::class
    |
    */

    'context' => [
        'roles' => false,
        'permissions' => false,
        'subject_roles' => false,
        'subject_permissions' => false,
        'features' => false,
        'subject_features' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Columns
    |--------------------------------------------------------------------------
    |
    | Configure which additional columns to sync from class attributes to
    | the database tables. Available columns: 'set', 'label', 'description'.
    |
    | Set to an empty array to only sync required columns (name, guard_name).
    |
    */

    'sync_columns' => [
        'roles' => ['set', 'label', 'description'],
        'permissions' => ['set', 'label', 'description'],
        'features' => ['label', 'description'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Sync
    |--------------------------------------------------------------------------
    |
    | When enabled, permissions, roles, and features will be automatically
    | synced to the database when the service provider boots. Disable in
    | production and use the artisan command instead.
    |
    */

    'auto_sync' => env('MANDATE_AUTO_SYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Features Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the feature flags integration.
    |
    | - enabled: Enable/disable feature flag support entirely
    | - have_permissions: Features can gate permissions
    | - have_roles: Features can gate roles
    |
    */

    'features' => [
        'enabled' => true,
        'have_permissions' => true,
        'have_roles' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery Directories
    |--------------------------------------------------------------------------
    |
    | Configure directories to scan for permission, role, and feature classes.
    | Format: [directory_path => namespace]
    |
    */

    'discovery' => [
        'permissions' => [
            app_path('Permissions') => 'App\\Permissions',
        ],
        'roles' => [
            app_path('Roles') => 'App\\Roles',
        ],
        'features' => [
            app_path('Features') => 'App\\Features',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    |
    | Enable wildcard permission matching. When enabled, you can assign
    | wildcard permissions like 'users.*' that match specific permissions
    | like 'users.view' or 'users.create'.
    |
    | Example:
    |   $user->grant('users.*');
    |   $user->hasPermission('users.view');  // true
    |   $user->hasPermission('users.create'); // true
    |
    */

    'wildcards' => env('MANDATE_WILDCARDS', false),

    /*
    |--------------------------------------------------------------------------
    | Gate Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, Mandate registers a Gate::before() hook that routes
    | Laravel's authorization checks through Mandate.
    |
    | Permission checks:
    |   $user->can('users.view')
    |   Gate::allows('users.view')
    |   @can('users.view') // Blade
    |   ->middleware('can:users.view')
    |
    | Feature checks:
    |   $user->can('feature-name')
    |   $user->can(FeatureClass::class)
    |
    */

    'gate_integration' => env('MANDATE_GATE_INTEGRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for discovered permissions, roles, and features.
    |
    | - ttl: Cache time-to-live in seconds. Set to 0 to disable caching.
    |        Default is 3600 (1 hour).
    |
    */

    'cache' => [
        'ttl' => env('MANDATE_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | TypeScript Export Path
    |--------------------------------------------------------------------------
    |
    | The path where the TypeScript permissions/roles file will be generated
    | when running the mandate:typescript command.
    |
    */

    'typescript_path' => resource_path('js/permissions.ts'),

];
