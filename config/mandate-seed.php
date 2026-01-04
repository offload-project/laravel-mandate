<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Role Permissions Mapping
    |--------------------------------------------------------------------------
    |
    | Map roles to their permissions. This configuration is used when seeding
    | role permissions via the `mandate:seed` command.
    |
    | Supports:
    | - Permission class references (all constants from the class)
    | - String permission names
    | - Wildcard patterns (when wildcards are enabled)
    |
    | IMPORTANT: These mappings are only applied when:
    | 1. A role is first created (during sync)
    | 2. Running `php artisan mandate:seed`
    |
    | By default, `mandate:sync` will NOT overwrite existing role-permission
    | relationships in the database. This allows you to manage permissions
    | via UI/database without config overwriting your changes.
    |
    | Use `mandate:seed` for initial setup or when you intentionally want
    | to reset role permissions to match this config.
    |
    | Example:
    |
    | use App\Permissions\UserPermissions;
    | use App\Permissions\PostPermissions;
    | use App\Roles\SystemRoles;
    |
    | 'role_permissions' => [
    |     SystemRoles::ADMIN => [
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
    | Feature Roles Mapping
    |--------------------------------------------------------------------------
    |
    | Map features to their roles. Roles listed here become available when
    | the feature is active.
    |
    | Supports:
    | - Role class references (all constants from the class)
    | - String role names
    |
    | Storage Strategy:
    | - If the role has a #[FeatureSet] attribute: stored on the role record
    |   with scope='feature' and context_model=FeatureClassName
    | - If the role appears under multiple features or has no #[FeatureSet]:
    |   stored in the feature_roles pivot table
    |
    | These mappings MERGE with #[FeatureSet] attributes on role classes.
    |
    | Example:
    |
    | use App\Features\BetaFeature;
    | use App\Roles\BetaTesterRole;
    |
    | 'feature_roles' => [
    |     BetaFeature::class => [
    |         BetaTesterRole::class,
    |     ],
    | ],
    |
    */

    'feature_roles' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Permissions Mapping
    |--------------------------------------------------------------------------
    |
    | Map features to their permissions. Permissions listed here become
    | available when the feature is active.
    |
    | Supports:
    | - Permission class references (all constants from the class)
    | - String permission names
    |
    | Storage Strategy:
    | - If the permission has a #[FeatureSet] attribute: stored on the
    |   permission record with scope='feature' and context_model=FeatureClassName
    | - If the permission appears under multiple features or has no #[FeatureSet]:
    |   stored in the feature_permissions pivot table
    |
    | These mappings MERGE with #[FeatureSet] attributes on permission classes.
    |
    | Example:
    |
    | use App\Features\BetaFeature;
    | use App\Features\AnalyticsFeature;
    | use App\Permissions\BetaPermissions;
    | use App\Permissions\AnalyticsPermissions;
    |
    | 'feature_permissions' => [
    |     BetaFeature::class => [
    |         BetaPermissions::class,
    |     ],
    |     AnalyticsFeature::class => [
    |         AnalyticsPermissions::VIEW,
    |         AnalyticsPermissions::EXPORT,
    |     ],
    | ],
    |
    */

    'feature_permissions' => [
        //
    ],

];
