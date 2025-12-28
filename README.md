<p align="center">
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/v/offload-project/laravel-mandate.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/offload-project/laravel-mandate/actions"><img src="https://img.shields.io/github/actions/workflow/status/offload-project/laravel-mandate/tests.yml?branch=main&style=flat-square" alt="GitHub Tests Action Status"></a>
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/dt/offload-project/laravel-mandate.svg?style=flat-square" alt="Total Downloads"></a>
</p>

# Laravel Mandate

A unified authorization management system for Laravel that brings together roles, permissions, and feature flags into a
single, type-safe API. Built
on [Spatie Laravel Permission](https://github.com/spatie/laravel-permission), [Laravel Pennant](https://laravel.com/docs/pennant),
and [Laravel Hoist](https://github.com/offload-project/laravel-hoist).

## Features

- **Unified Authorization**: Manage roles, permissions, and feature access through a single API
- **Type-Safe**: Class-based permissions and roles using constants with PHP attributes
- **Feature-Gated Access**: Tie permissions and roles to feature flags - only active when the feature is enabled
- **Auto-Discovery**: Automatically discover permission and role classes from configured directories
- **Database Sync**: Sync discovered permissions and roles to Spatie's database tables with events
- **Optional Metadata**: Store set, label, and description in the database for UI filtering
- **Middleware**: Feature-aware route protection out of the box
- **Events**: Listen to sync operations with `PermissionsSynced`, `RolesSynced`, and `MandateSynced` events
- **TypeScript Export**: Generate TypeScript constants from your PHP permission/role classes for type-safe frontend usage
- **Testable**: Contracts/interfaces for all registries enable easy mocking in tests

## Requirements

- PHP 8.4+
- Laravel 11+
- Laravel Pennant 1.0+
- Laravel Hoist 1.0+
- Spatie Laravel Permission 6.0+
- Spatie Laravel Data 4.0+

## Installation

> See the [Complete Setup Guide](docs/SETUP.md) for step-by-step instructions including all dependency setup.

```bash
composer require offload-project/laravel-mandate
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=mandate-config
php artisan vendor:publish --tag=mandate-migrations
```

## Quick Start

### 1. Create Permission Classes

```bash
php artisan mandate:permission UserPermissions --set=users
```

```php
// app/Permissions/UserPermissions.php
use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\PermissionsSet;

#[PermissionsSet('users')]
final class UserPermissions
{
    #[Label('View Users')]
    public const string VIEW = 'view users';

    #[Label('Create Users')]
    public const string CREATE = 'create users';

    #[Label('Update Users')]
    public const string UPDATE = 'update users';

    #[Label('Delete Users')]
    public const string DELETE = 'delete users';

    #[Label('Export Users'), Description('Export user data to CSV')]
    public const string EXPORT = 'export users';
}
```

### 2. Create Role Classes

```bash
php artisan mandate:role SystemRoles --set=system
```

```php
// app/Roles/SystemRoles.php
use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('system')]
final class SystemRoles
{
    #[Label('Administrator'), Description('Full system access')]
    public const string ADMINISTRATOR = 'administrator';

    #[Label('Editor')]
    public const string EDITOR = 'editor';

    #[Label('Viewer')]
    public const string VIEWER = 'viewer';
}
```

### 3. Map Roles to Permissions (Config)

```php
// config/mandate.php
use App\Permissions\UserPermissions;
use App\Permissions\PostPermissions;
use App\Roles\SystemRoles;

'role_permissions' => [
    SystemRoles::ADMINISTRATOR => [
        UserPermissions::class,     // All user permissions
        PostPermissions::class,     // All post permissions
    ],

    SystemRoles::EDITOR => [
        UserPermissions::VIEW,
        PostPermissions::VIEW,
        PostPermissions::CREATE,
        PostPermissions::UPDATE,
    ],

    SystemRoles::VIEWER => [
        UserPermissions::VIEW,
        PostPermissions::VIEW,
    ],
],
```

### 4. Define Feature Gates (Optional)

Features control which permissions/roles are available:

```php
// app/Features/ExportFeature.php
class ExportFeature
{
    public string $name = 'export';
    public string $label = 'Export Feature';

    public function permissions(): array
    {
        return [
            UserPermissions::EXPORT,
            PostPermissions::EXPORT,
        ];
    }

    public function roles(): array
    {
        return [
            // Roles gated by this feature
        ];
    }

    public function resolve($user): bool
    {
        return $user->plan === 'enterprise';
    }
}
```

### 5. Sync to Database

```bash
# Initial setup - seeds role permissions from config
php artisan mandate:sync --seed

# Subsequent syncs - only adds new permissions/roles, preserves DB relationships
php artisan mandate:sync
```

## Usage

### Type-Safe Permission Checks

```php
use App\Permissions\UserPermissions;
use App\Roles\SystemRoles;
use OffloadProject\Mandate\Facades\Mandate;

// Check permission (considers feature flags)
if (Mandate::can($user, UserPermissions::EXPORT)) {
    // User has permission AND the export feature is enabled
}

// Check role (considers feature flags)
if (Mandate::hasRole($user, SystemRoles::ADMINISTRATOR)) {
    // User has role AND any feature requirement is met
}

// Direct Spatie usage still works
$user->hasPermissionTo(UserPermissions::VIEW);
$user->hasRole(SystemRoles::EDITOR);
```

### Middleware

Protect routes with feature-aware authorization:

```php
use App\Permissions\UserPermissions;
use App\Roles\SystemRoles;
use OffloadProject\Mandate\Http\Middleware\MandatePermission;
use OffloadProject\Mandate\Http\Middleware\MandateRole;

// String-based (in routes)
Route::get('/users/export', ExportController::class)
    ->middleware('mandate.permission:export users');

Route::get('/admin', AdminController::class)
    ->middleware('mandate.role:administrator');

Route::get('/premium', PremiumController::class)
    ->middleware('mandate.feature:App\Features\PremiumFeature');

// Multiple permissions/roles (OR logic)
Route::get('/users', UserController::class)
    ->middleware('mandate.permission:view users,users.list');

// Type-safe with constants
Route::get('/users/export', ExportController::class)
    ->middleware(MandatePermission::using(UserPermissions::EXPORT));

Route::get('/admin', AdminController::class)
    ->middleware(MandateRole::using(SystemRoles::ADMINISTRATOR, SystemRoles::EDITOR));
```

Available middleware:

- `mandate.permission:{permissions}` - Check permission(s) with feature awareness
- `mandate.role:{roles}` - Check role(s) with feature awareness
- `mandate.feature:{class}` - Check if feature is active

### Getting Data for UI

```php
// All permissions (for admin UI)
$permissions = Mandate::permissions()->all();

// Permissions grouped by set
$grouped = Mandate::permissions()->grouped();

// Permissions for a user (with status)
$userPermissions = Mandate::permissions()->forModel($user);
// Returns: [{ name, label, set, active, featureActive, granted }, ...]

// Only granted permissions (has + feature active)
$granted = Mandate::grantedPermissions($user);

// Only available permissions (feature is on)
$available = Mandate::availablePermissions($user);

// Same methods for roles
$roles = Mandate::roles()->all();
$assigned = Mandate::assignedRoles($user);
$available = Mandate::availableRoles($user);
```

### Querying Features

```php
// Get feature with its permissions and roles
$feature = Mandate::feature(ExportFeature::class);
$feature->permissions; // Permissions this feature gates
$feature->roles;       // Roles this feature gates

// All features
$features = Mandate::features()->all();

// Features for a user (with active status)
$userFeatures = Mandate::features()->forModel($user);
```

### Syncing to Database

By default, syncing only creates new permissions and roles without modifying existing role-permission
relationships. This allows you to manage permissions via UI/database without config overwriting your changes.

```php
// Sync (creates new permissions/roles, preserves existing relationships)
Mandate::sync();

// Sync with seeding (resets role permissions to match config)
Mandate::sync(seed: true);

// Sync only permissions
Mandate::syncPermissions();

// Sync only roles (without touching existing permissions)
Mandate::syncRoles();

// Sync roles and seed permissions from config
Mandate::syncRoles(seed: true);

// Sync with specific guard
Mandate::sync('api');
```

## Configuration

```php
// config/mandate.php
return [
    // Directories to scan for permission classes
    'permission_directories' => [
        app_path('Permissions') => 'App\\Permissions',
    ],

    // Directories to scan for role classes
    'role_directories' => [
        app_path('Roles') => 'App\\Roles',
    ],

    // Map roles to their permissions
    'role_permissions' => [
        // SystemRoles::ADMINISTRATOR => [UserPermissions::class],
    ],

    // Sync additional columns to database (requires migration)
    // Options: true (all), ['set', 'label'], or false (none)
    'sync_columns' => false,

    // Auto-sync on boot (disable in production)
    'auto_sync' => env('MANDATE_AUTO_SYNC', false),

    // TypeScript export path (null to require --output option)
    'typescript_path' => resource_path('js/permissions.ts'),
];
```

## Syncing Additional Columns

Optionally sync metadata from your permission and role classes to the database.
This allows you to group and filter permissions/roles in your UI.

Available columns:

- `set` - The set name from `#[PermissionsSet]` or `#[RoleSet]`
- `label` - The label from `#[Label]` attribute
- `description` - The description from `#[Description]` attribute

### Setup

1. Publish and run the migration:
   ```bash
   php artisan vendor:publish --tag=mandate-migrations
   php artisan migrate
   ```

2. Enable in config:
   ```php
   // Sync all columns (set, label, description)
   'sync_columns' => true,

   // Or sync specific columns only
   'sync_columns' => ['set', 'label'],
   ```

3. Sync to populate the columns:
   ```bash
   php artisan mandate:sync
   ```

### Usage

Once enabled, columns will be:

- Populated when creating new permissions/roles
- Updated when running sync if values changed
- Available for querying in your application

```php
// Query permissions by set
$permissions = Permission::where('set', 'users')->get();

// Group in UI
$grouped = Permission::all()->groupBy('set');

// Display labels in UI
foreach ($permissions as $permission) {
    echo $permission->label ?? $permission->name;
}
```

## TypeScript Export

Generate a TypeScript file containing your permissions and roles as constants for type-safe frontend usage.

### Generate TypeScript File

```bash
# Generate using configured path (default: resources/js/permissions.ts)
php artisan mandate:typescript

# Generate to a custom path
php artisan mandate:typescript --output=resources/js/auth/permissions.ts
```

### Output Format

The command generates a TypeScript file with your permissions, roles, and features:

```typescript
// This file is auto-generated by mandate:typescript. Do not edit manually.

export const UserPermissions = {
  VIEW: "view users",
  CREATE: "create users",
  UPDATE: "update users",
  DELETE: "delete users",
  EXPORT: "export users",
} as const;

export const SystemRoles = {
  ADMINISTRATOR: "administrator",
  EDITOR: "editor",
  VIEWER: "viewer",
} as const;

export const Features = {
  ExportFeature: "export",
  PremiumFeature: "premium",
} as const;
```

### Configuration

Configure the default output path in your config file:

```php
// config/mandate.php
'typescript_path' => resource_path('js/permissions.ts'),
```

### Frontend Usage

Use the generated constants for type-safe permission and feature checks:

```typescript
import { UserPermissions, SystemRoles, Features } from './permissions';

// Type-safe permission checking
function canExport(userPermissions: string[]): boolean {
  return userPermissions.includes(UserPermissions.EXPORT);
}

// Type-safe feature checking
function isFeatureEnabled(activeFeatures: string[]): boolean {
  return activeFeatures.includes(Features.ExportFeature);
}

// TypeScript will catch typos at compile time
if (user.permissions.includes(UserPermissions.VIWE)) {
  // ❌ TypeScript error: Property 'VIWE' does not exist
}

// Create union types from permissions
type UserPermission = typeof UserPermissions[keyof typeof UserPermissions];
// Result: "view users" | "create users" | "update users" | "delete users" | "export users"

type FeatureName = typeof Features[keyof typeof Features];
// Result: "export" | "premium"
```

## How It Works

### The Authorization Flow

```
User wants to perform action requiring UserPermissions::EXPORT
                    │
                    ▼
    ┌───────────────────────────────┐
    │  Does user have permission?   │──── No ────▶ Denied
    │     (via Spatie)              │
    └───────────────────────────────┘
                    │
                   Yes
                    │
                    ▼
    ┌───────────────────────────────┐
    │  Is permission tied to a      │
    │  feature flag?                │──── No ────▶ Granted
    └───────────────────────────────┘
                    │
                   Yes
                    │
                    ▼
    ┌───────────────────────────────┐
    │  Is feature active for user?  │──── No ────▶ Denied
    │     (via Pennant)             │
    └───────────────────────────────┘
                    │
                   Yes
                    │
                    ▼
                 Granted
```

### Permission Status in UI

| Permission   | Has Permission | Feature Active | Status              |
|--------------|----------------|----------------|---------------------|
| View Users   | ✓              | N/A            | ✅ Granted           |
| Export Users | ✓              | ✗              | 🔒 Requires upgrade |
| Delete Users | ✗              | ✓              | ❌ Not assigned      |

## Attributes

### Permission Classes

| Attribute                   | Target            | Description                            |
|-----------------------------|-------------------|----------------------------------------|
| `#[PermissionsSet('name')]` | Class             | Groups permissions together (required) |
| `#[Label('Human Name')]`    | Constant          | Human-readable label                   |
| `#[Description('Details')]` | Constant          | Detailed description                   |
| `#[Guard('web')]`           | Class or Constant | Auth guard to use                      |

### Role Classes

| Attribute                   | Target            | Description                      |
|-----------------------------|-------------------|----------------------------------|
| `#[RoleSet('name')]`        | Class             | Groups roles together (required) |
| `#[Label('Human Name')]`    | Constant          | Human-readable label             |
| `#[Description('Details')]` | Constant          | Detailed description             |
| `#[Guard('web')]`           | Class or Constant | Auth guard to use                |

## Artisan Commands

```bash
# Create a permission class
php artisan mandate:permission UserPermissions --set=users

# Create a role class
php artisan mandate:role SystemRoles --set=system

# Sync permissions and roles to database
php artisan mandate:sync                  # Creates new, preserves existing relationships
php artisan mandate:sync --seed           # Seeds role permissions from config (initial setup)
php artisan mandate:sync --permissions    # Only permissions
php artisan mandate:sync --roles          # Only roles
php artisan mandate:sync --guard=api      # Specific guard

# Generate TypeScript file with permissions and roles
php artisan mandate:typescript                      # Uses configured path
php artisan mandate:typescript --output=custom.ts   # Custom output path
```

> **Note:** Use `--seed` for initial setup or when you intentionally want to reset role permissions to match
> config. Without `--seed`, the database is authoritative for role-permission relationships.

## Events

Mandate dispatches events during sync operations, allowing you to hook into the sync lifecycle:

```php
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\Events\MandateSynced;

// Listen to permission sync
Event::listen(PermissionsSynced::class, function (PermissionsSynced $event) {
    Log::info('Permissions synced', [
        'created' => $event->created,
        'existing' => $event->existing,
        'updated' => $event->updated,
        'guard' => $event->guard,
    ]);
});

// Listen to role sync
Event::listen(RolesSynced::class, function (RolesSynced $event) {
    Log::info('Roles synced', [
        'created' => $event->created,
        'existing' => $event->existing,
        'updated' => $event->updated,
        'permissions_synced' => $event->permissionsSynced,
        'seeded' => $event->seeded,
    ]);
});

// Listen to full sync (both permissions and roles)
Event::listen(MandateSynced::class, function (MandateSynced $event) {
    // $event->permissions - permission sync stats
    // $event->roles - role sync stats
    // $event->guard - guard used
    // $event->seeded - whether --seed was used
});
```

## Testing Your Application

### Using Contracts for Mocking

Mandate provides contracts (interfaces) for all registries, making it easy to mock in tests:

```php
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;

// In your test
public function test_something_with_permissions()
{
    $mockRegistry = Mockery::mock(PermissionRegistryContract::class);
    $mockRegistry->shouldReceive('can')->with($user, 'view users')->andReturn(true);

    $this->app->instance(PermissionRegistryContract::class, $mockRegistry);

    // Your test...
}
```

## Testing

```bash
./vendor/bin/pest
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
