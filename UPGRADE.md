# Upgrade Guide

This document covers breaking changes when upgrading from Laravel Mandate 1.x (Spatie-based) to 2.x (standalone).

## Overview

Version 2.x is a **complete rewrite** of Laravel Mandate. It is now a standalone RBAC package that does not depend on
Spatie Laravel Permission. This provides a cleaner, more focused API with additional features like capabilities,
multi-tenancy, and wildcard permissions.

## Major Breaking Changes

### 1. Spatie Laravel Permission Dependency Removed

**Before (1.x):**

```json
{
  "require": {
    "spatie/laravel-permission": "^6.24.0",
    "spatie/laravel-data": "^4.18"
  }
}
```

**After (2.x):**

- No Spatie dependencies
- Standalone implementation with its own models and traits

**Migration:** You must migrate your data from Spatie's tables to Mandate's tables.
See [Database Migration](#database-migration) below.

---

### 2. Trait Changes

**Before (1.x):**

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

**After (2.x):**

```php
use OffloadProject\Mandate\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

---

### 3. Facade API Changes

**Before (1.x):**

```php
use OffloadProject\Mandate\Facades\Mandate;

// Check permissions
Mandate::can($user, UserPermissions::VIEW);
Mandate::hasRole($user, SystemRoles::ADMIN);

// Get permissions/roles
Mandate::grantedPermissions($user);
Mandate::assignedRoles($user);
Mandate::availablePermissions($user);

// Registry access
Mandate::permissions()->forModel($user);
Mandate::roles()->forModel($user);
```

**After (2.x):**

```php
// Check permissions - directly on model
$user->hasPermission('user:view');
$user->hasRole('admin');

// Or using facade for fluent checks
use OffloadProject\Mandate\Facades\Mandate;

Mandate::for($user)->can('user:view');
Mandate::for($user)->is('admin');

// Get permissions/roles - directly on model
$user->getAllPermissions();
$user->getRoleNames();
$user->getDirectPermissions();
$user->getPermissionsViaRoles();
```

---

### 4. Attribute Changes

**Before (1.x):**

```php
use OffloadProject\Mandate\Attributes\PermissionsSet;
use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\Description;

#[PermissionsSet('users', label: 'User Permissions')]
class UserPermissions
{
    #[Label('View Users')]
    #[Description('Can view user list')]
    public const VIEW = 'user:view';
}

#[RoleSet('system')]
#[Inherits(EditorRole::class)]
class AdminRole
{
    public const ADMIN = 'admin';
}
```

**After (2.x):**

```php
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\Description;

#[Guard('web')]
#[Label('User Permissions')]
class UserPermissions
{
    #[Label('View Users')]
    #[Description('Can view user list')]
    public const VIEW = 'user:view';
}

#[Guard('web')]
class SystemRoles
{
    #[Label('Administrator')]
    public const ADMIN = 'admin';
}
```

**Key Differences:**

- `#[PermissionsSet]` → Use **Capabilities** instead (see [Capabilities](#8-capabilities-replace-permissionsset))
- `#[RoleSet]` → Removed (all classes with string constants in configured paths are discovered)
- `#[Inherits]` → Removed (use config-based role-permission assignments instead)
- `#[Guard]` → New attribute for specifying authentication guard

---

### 5. Configuration Changes

**Before (1.x):**

```php
// config/mandate.php
return [
    'permission_directories' => [app_path('Permissions')],
    'role_directories' => [app_path('Roles')],
    'role_permissions' => [
        'admin' => ['user:*', 'article:*'],
    ],
    'sync_columns' => ['set', 'label', 'description'],
    'auto_sync' => env('MANDATE_AUTO_SYNC', false),
    'typescript_path' => resource_path('js/permissions.ts'),
];
```

**After (2.x):**

```php
// config/mandate.php
return [
    // Role-permission assignments (works with or without code-first)
    'assignments' => [
        'admin' => [
            'permissions' => ['user:*', 'article:*'],
            'capabilities' => ['user-management'],
        ],
    ],

    // Code-first is now optional (disabled by default)
    'code_first' => [
        'enabled' => false,
        'paths' => [
            'permissions' => app_path('Permissions'),
            'roles' => app_path('Roles'),
            'capabilities' => app_path('Capabilities'),
        ],
        'typescript_path' => resource_path('js/types/mandate.ts'),
    ],

    // Many new options
    'wildcards' => ['enabled' => false],
    'capabilities' => ['enabled' => false],
    'context' => ['enabled' => false],
    'features' => ['enabled' => false],
    // ... see full config for all options
];
```

---

### 6. Command Changes

**Before (1.x):**

```bash
php artisan mandate:sync
php artisan mandate:sync --seed
php artisan mandate:permission UserPermissions
php artisan mandate:role SystemRoles
```

**After (2.x):**

```bash
# Code-first commands (generate PHP classes) - default behavior
php artisan mandate:permission UserPermissions
php artisan mandate:role SystemRoles
php artisan mandate:capability ContentCapabilities

# Database-first commands (use --db flag)
php artisan mandate:permission user:view --db
php artisan mandate:role admin --db
php artisan mandate:capability manage-posts --db

# Sync code-first definitions to database
php artisan mandate:sync
php artisan mandate:sync --seed
php artisan mandate:sync --dry-run

# TypeScript generation (enhanced - now merges code-first AND database)
php artisan mandate:typescript
php artisan mandate:typescript --output=resources/js/types/mandate.ts
```

**TypeScript generation changes:**

- No longer requires `code_first.enabled = true`
- Merges both code-first definitions and database records
- Database records are grouped by prefix (e.g., `article:view` → `ArticlePermissions`)
- Code-first definitions take precedence over database records with the same name
- Config path moved to `code_first.typescript_path`

---

### 7. Feature Flag Integration Changes

**Before (1.x):**

- Direct integration with Laravel Pennant and Laravel Hoist
- Feature flags checked automatically via registries

**After (2.x):**

- Feature integration is optional and delegates to external packages
- Implement `FeatureAccessHandler` contract for your feature package
- Feature checks only occur when using Feature models as context

```php
// Before: Automatic integration
Mandate::can($user, Permission::EXPORT); // Checked feature flags internally

// After: Explicit feature context
$user->hasPermission('export', $feature); // Checks via FeatureAccessHandler
$user->hasPermission('export', $feature, bypassFeatureCheck: true); // Skip feature check
```

---

### 8. Capabilities Replace PermissionsSet

The `#[PermissionsSet]` attribute from 1.x grouped permissions with a label and description. In 2.x, **Capabilities**
serve this purpose but with more power — they can be assigned to roles and optionally directly to user:

**Before (1.x):**

```php
#[PermissionsSet('users', label: 'User Management', description: 'Manage users')]
class UserPermissions
{
    public const VIEW = 'user:view';
    public const CREATE = 'user:create';
    public const EDIT = 'user:edit';
    public const DELETE = 'user:delete';
}
```

**After (2.x):**

```php
// 1. Define your permissions (no set grouping needed)
#[Guard('web')]
class UserPermissions
{
    #[Label('View Users')]
    public const VIEW = 'user:view';

    #[Label('Create Users')]
    public const CREATE = 'user:create';

    #[Label('Edit Users')]
    public const EDIT = 'user:edit';

    #[Label('Delete Users')]
    public const DELETE = 'user:delete';
}

// 2. Create a capability to group them
$capability = Capability::create([
    'name' => 'user-management',
    'label' => 'User Management',
    'description' => 'Manage users',
]);
$capability->grantPermission(['user:view', 'user:create', 'user:edit', 'user:delete']);

// 3. Assign the capability to roles
$adminRole->assignCapability('user-management');
```

**Benefits of Capabilities over PermissionsSet:**

- Capabilities can be assigned to roles (and optionally to users directly)
- Permission-to-capability relationships are stored in the database, not just metadata
- Users can check `$user->hasCapability('user-management')` directly
- Blade directives: `@capability('user-management')` ... `@endcapability`

---

### 9. New Features in 2.x

These features are new and have no equivalent in 1.x:

#### Capabilities (Enhanced from PermissionsSet)

Group permissions into assignable capabilities:

```php
$capability = Capability::create(['name' => 'manage-posts']);
$capability->grantPermission(['post:create', 'post:edit', 'post:delete']);
$role->assignCapability('manage-posts');
```

#### Context Model (Multi-Tenancy)

Scope roles and permissions to context models:

```php
$user->assignRole('manager', $team);
$user->hasPermission('projects.edit', $team);
```

#### Wildcard Permissions

Pattern-based permission matching:

```php
$user->grantPermission('article:*');
$user->hasPermission('article:edit'); // true
```

#### Blade Directives

```blade
@role('admin') ... @endrole
@permission('article:edit') ... @endpermission
@capability('manage-posts') ... @endcapability
```

#### Route Middleware

```php
Route::get('/admin')->middleware('role:admin');
Route::get('/articles')->middleware('permission:article:view');
```

---

## Database Migration

### Automated Migration from Spatie

Mandate includes a command to automatically migrate data from Spatie Laravel Permission:

```bash
# Preview what will be migrated (recommended first step)
php artisan mandate:upgrade-from-spatie --dry-run

# Run the migration
php artisan mandate:upgrade-from-spatie

# Skip specific parts if needed
php artisan mandate:upgrade-from-spatie --skip-permissions
php artisan mandate:upgrade-from-spatie --skip-roles
php artisan mandate:upgrade-from-spatie --skip-assignments

# Create capabilities from permission prefixes (e.g., "user:view" → "user-management")
php artisan mandate:upgrade-from-spatie --create-capabilities

# Convert #[PermissionsSet] classes to capabilities
php artisan mandate:upgrade-from-spatie --convert-permission-sets
php artisan mandate:upgrade-from-spatie --convert-permission-sets --permission-sets-path=app/Permissions
```

**What the command migrates:**

1. **Permissions** - From Spatie's `permissions` table to Mandate's
2. **Roles** - From Spatie's `roles` table to Mandate's
3. **Role-Permission Assignments** - From `role_has_permissions` to `permission_role`
4. **User-Role Assignments** - From `model_has_roles` to `role_subject`
5. **User-Permission Assignments** - From `model_has_permissions` to `permission_subject`

**Optional Capability Creation:**

When using `--create-capabilities`, the command groups permissions by their prefix and creates
capabilities. For example, permissions like `user:view`, `user:create`, `user:edit` would create
a `user-management` capability containing all those permissions.

**Converting PermissionsSets (1.x code-first):**

If you used the code-first approach in 1.x with `#[PermissionsSet]` attributes, use
`--convert-permission-sets` to convert them to Capabilities:

```php
// Before (1.x)
#[PermissionsSet('users', label: 'User Management', description: 'Manage users')]
class UserPermissions
{
    public const VIEW = 'user:view';
    public const CREATE = 'user:create';
}

// After: Creates a "users-management" capability with those permissions
```

The command reads the set name, label, and description from the attribute and creates a
capability with all the permission constants from that class.

### Manual Migration

If you prefer manual control, here's the migration logic:

```php
use Illuminate\Support\Facades\DB;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

// Migrate permissions
$spatiePermissions = DB::table('permissions')->get();
foreach ($spatiePermissions as $permission) {
    Permission::create([
        'name' => $permission->name,
        'guard' => $permission->guard_name,
    ]);
}

// Migrate roles
$spatieRoles = DB::table('roles')->get();
foreach ($spatieRoles as $role) {
    Role::create([
        'name' => $role->name,
        'guard' => $role->guard_name,
    ]);
}

// Migrate role_has_permissions
$rolePermissions = DB::table('role_has_permissions')->get();
foreach ($rolePermissions as $rp) {
    $role = Role::find($rp->role_id);
    $permission = Permission::find($rp->permission_id);
    if ($role && $permission) {
        $role->grantPermission($permission);
    }
}

// Migrate model_has_roles (user roles)
$modelRoles = DB::table('model_has_roles')->get();
foreach ($modelRoles as $mr) {
    $model = $mr->model_type::find($mr->model_id);
    $role = Role::where('id', $mr->role_id)->first();
    if ($model && $role) {
        $model->assignRole($role);
    }
}

// Migrate model_has_permissions (direct user permissions)
$modelPermissions = DB::table('model_has_permissions')->get();
foreach ($modelPermissions as $mp) {
    $model = $mp->model_type::find($mp->model_id);
    $permission = Permission::where('id', $mp->permission_id)->first();
    if ($model && $permission) {
        $model->grantPermission($permission);
    }
}
```

### Table Name Changes

| Spatie Table            | Mandate Table        |
|-------------------------|----------------------|
| `permissions`           | `permissions`        |
| `roles`                 | `roles`              |
| `role_has_permissions`  | `permission_role`    |
| `model_has_roles`       | `role_subject`       |
| `model_has_permissions` | `permission_subject` |

---

## Step-by-Step Upgrade

1. **Update composer.json:**
   ```bash
   composer remove spatie/laravel-permission spatie/laravel-data
   composer require offload-project/laravel-mandate:^2.0
   ```

2. **Publish and run migrations:**
   ```bash
   # Core migrations (required)
   php artisan vendor:publish --tag=mandate-migrations

   # Optional: Capabilities feature
   php artisan vendor:publish --tag=mandate-migrations-capabilities

   # Optional: Metadata columns (label/description)
   php artisan vendor:publish --tag=mandate-migrations-meta

   php artisan migrate
   ```

3. **Update User model trait:**
   ```php
   // Change from
   use Spatie\Permission\Traits\HasRoles;
   // To
   use OffloadProject\Mandate\Concerns\HasRoles;
   ```

4. **Update configuration:**
   ```bash
   php artisan vendor:publish --tag=mandate-config
   ```
   Then migrate your settings from the old config format to the new one.

5. **Migrate database data:**
   ```bash
   # Preview first
   php artisan mandate:upgrade-from-spatie --dry-run

   # Run the migration
   php artisan mandate:upgrade-from-spatie
   ```

6. **Update permission/role class definitions:**
    - Remove `#[PermissionsSet]` and `#[RoleSet]` attributes
    - Add `#[Guard('web')]` attribute to classes
    - Remove `#[Inherits]` and use config-based assignments instead

7. **Update code using the Mandate facade:**
    - Change `Mandate::can($user, $perm)` to `$user->hasPermission($perm)`
    - Change `Mandate::hasRole($user, $role)` to `$user->hasRole($role)`

8. **Enable code-first if needed:**
   ```php
   // config/mandate.php
   'code_first' => [
       'enabled' => true,
       // ...
   ],
   ```

9. **Sync definitions:**
   ```bash
   php artisan mandate:sync --seed
   ```

10. **Update tests** to use the new API

---

## Getting Help

If you encounter issues during upgrade,
please [open an issue](https://github.com/offload-project/laravel-mandate/issues) with:

- Your Laravel version
- Your PHP version
- The specific error or issue you're facing
- Relevant code snippets
