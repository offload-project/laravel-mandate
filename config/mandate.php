<?php

declare(strict_types=1);

return [
    /*
    |==========================================================================
    | COMMONLY CUSTOMIZED OPTIONS
    |==========================================================================
    |
    | These are the settings most users will want to review and customize.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Capabilities
    |--------------------------------------------------------------------------
    |
    | Capabilities are semantic groupings of permissions. When enabled,
    | permissions can be organized into capabilities which can then be
    | assigned to roles or directly to subjects.
    |
    | enabled: Whether the capabilities feature is active
    | direct_assignment: Allow assigning capabilities directly to subjects
    |                    (bypassing roles). Creates capability_subject table.
    |
    */

    'capabilities' => [
        'enabled' => false,
        'direct_assignment' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Model (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Context Model support enables scoping roles and permissions to a
    | polymorphic context (e.g., Team, Organization, Project). This allows
    | multi-tenancy and resource-specific permission scenarios.
    |
    | enabled: Whether context model support is active
    | global_fallback: When checking permissions with a context, also check
    |                  for global (null context) permissions as fallback
    |
    */

    'context' => [
        'enabled' => false,
        'global_fallback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Integration
    |--------------------------------------------------------------------------
    |
    | Feature integration enables Mandate to delegate feature access checks
    | to an external package (e.g., Hoist) when a Feature model is used as
    | a context. This requires context model support to be enabled.
    |
    | enabled: Whether feature integration is active
    | models: Model class(es) that are considered Feature contexts
    | on_missing_handler: Behavior when feature handler is not available
    |                     'allow' = fail open (allow access)
    |                     'deny' = fail closed (deny access)
    |                     'throw' = throw exception
    |
    */

    'features' => [
        'enabled' => false,
        'models' => [
            // App\Models\Feature::class,
        ],
        'on_missing_handler' => 'deny',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    |
    | Enable wildcard permission matching (e.g., "article:*" matches
    | "article:view", "article:edit", etc.)
    |
    | enabled: Whether wildcard matching is active
    | handler: Custom wildcard handler class (must implement WildcardHandler)
    |
    */

    'wildcards' => [
        'enabled' => false,
        'handler' => OffloadProject\Mandate\WildcardPermission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | When enabled, Mandate will fire events when roles/permissions are
    | assigned or revoked. Disabled by default for performance.
    |
    */

    'events' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Gate Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, permissions are automatically registered with Laravel's
    | authorization Gate, allowing use of @can directives and Gate::allows().
    |
    */

    'register_gate' => true,

    /*
    |==========================================================================
    | ADVANCED OPTIONS (most apps use defaults)
    |==========================================================================
    |
    | The settings below rarely need customization. They're provided for
    | advanced use cases like custom table names, UUID primary keys, or
    | specific caching requirements.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Model Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The data type for primary keys on Mandate models (permissions, roles,
    | capabilities). This affects both primary keys and foreign keys.
    |
    | Supported: 'int' (auto-incrementing bigint), 'uuid', 'ulid'
    |
    */

    'model_id_type' => 'int',

    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | Customize the model classes used by Mandate. Your custom models must
    | implement the corresponding contract interfaces.
    |
    */

    'models' => [
        'permission' => OffloadProject\Mandate\Models\Permission::class,
        'role' => OffloadProject\Mandate\Models\Role::class,
        'capability' => OffloadProject\Mandate\Models\Capability::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database table names used by Mandate.
    |
    */

    'tables' => [
        'permissions' => 'permissions',
        'roles' => 'roles',
        'capabilities' => 'capabilities',
        'permission_role' => 'permission_role',
        'permission_subject' => 'permission_subject',
        'role_subject' => 'role_subject',
        'capability_permission' => 'capability_permission',
        'capability_role' => 'capability_role',
        'capability_subject' => 'capability_subject',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    | Customize the column names used in pivot tables.
    |
    | For morph columns, specify the base name (e.g., 'subject') and the
    | system will automatically append '_id' and '_type' suffixes.
    |
    */

    'column_names' => [
        'role_id' => 'role_id',
        'permission_id' => 'permission_id',
        'capability_id' => 'capability_id',
        'subject_morph_name' => 'subject',
        'context_morph_name' => 'context',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure how permissions are cached for performance optimization.
    |
    | expiration: Cache TTL in seconds (default: 24 hours)
    | key: The cache key prefix used for storing permissions
    | store: The cache store to use (null = default store)
    |
    */

    'cache' => [
        'expiration' => 60 * 60 * 24, // 24 hours
        'key' => 'mandate.permissions.cache',
        'store' => null,
    ],

];
