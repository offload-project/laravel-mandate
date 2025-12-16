# Laravel Mandate Setup Guide

This guide walks you through the complete setup of Laravel Mandate and all its dependencies.

## Prerequisites

- PHP 8.4+
- Laravel 11+
- Composer

## Step 1: Install Spatie Laravel Permission

Spatie Laravel Permission provides the underlying roles and permissions system.

### Install the package

```bash
composer require spatie/laravel-permission
```

### Publish and run migrations

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

This creates the following tables:
- `permissions`
- `roles`
- `model_has_permissions`
- `model_has_roles`
- `role_has_permissions`

### Add the trait to your User model

```php
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // ...
}
```

### Clear config cache

```bash
php artisan config:clear
```

For more details, see the [Spatie Laravel Permission documentation](https://spatie.be/docs/laravel-permission/v6/introduction).

---

## Step 2: Install Laravel Pennant

Laravel Pennant provides feature flag functionality for gating permissions and roles.

### Install the package

```bash
composer require laravel/pennant
```

### Publish and run migrations

```bash
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

This creates the `features` table for storing feature flag states.

### Configuration

The published config file is at `config/pennant.php`. The default database driver works well for most applications:

```php
// config/pennant.php
return [
    'default' => env('PENNANT_STORE', 'database'),

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'features',
        ],
    ],
];
```

For more details, see the [Laravel Pennant documentation](https://laravel.com/docs/pennant).

---

## Step 3: Install Laravel Hoist

Laravel Hoist enables auto-discovery of feature classes from configured directories.

### Install the package

```bash
composer require offload-project/laravel-hoist
```

### Publish configuration (optional)

```bash
php artisan vendor:publish --tag=hoist-config
```

Laravel Hoist automatically discovers feature classes. No additional setup is required for basic usage.

For more details, see the [Laravel Hoist documentation](https://github.com/offload-project/laravel-hoist).

---

## Step 4: Install Spatie Laravel Data

Spatie Laravel Data provides the data transfer objects used by Mandate.

### Install the package

```bash
composer require spatie/laravel-data
```

### Publish configuration (optional)

```bash
php artisan vendor:publish --tag="data-config"
```

For more details, see the [Spatie Laravel Data documentation](https://spatie.be/docs/laravel-data/v4/introduction).

---

## Step 5: Install Laravel Mandate

Now install Laravel Mandate itself.

### Install the package

```bash
composer require offload-project/laravel-mandate
```

### Publish configuration

```bash
php artisan vendor:publish --tag=mandate-config
```

### Configure directories

Edit `config/mandate.php` to set up your permission and role directories:

```php
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
        // Define after creating your permission and role classes
    ],

    // Sync additional columns to database (requires migration)
    'sync_columns' => false,

    // Auto-sync on boot (disable in production)
    'auto_sync' => env('MANDATE_AUTO_SYNC', false),
];
```

### Create directories

```bash
mkdir -p app/Permissions app/Roles app/Features
```

---

## Step 6: Create Your First Permission Class

```bash
php artisan mandate:permission UserPermissions --set=users
```

Edit the generated file:

```php
// app/Permissions/UserPermissions.php
<?php

declare(strict_types=1);

namespace App\Permissions;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\PermissionsSet;

#[PermissionsSet('users')]
final class UserPermissions
{
    #[Label('View Users')]
    public const string VIEW = 'users.view';

    #[Label('Create Users')]
    public const string CREATE = 'users.create';

    #[Label('Update Users')]
    public const string UPDATE = 'users.update';

    #[Label('Delete Users'), Description('Permanently delete users from the system')]
    public const string DELETE = 'users.delete';
}
```

---

## Step 7: Create Your First Role Class

```bash
php artisan mandate:role SystemRoles --set=system
```

Edit the generated file:

```php
// app/Roles/SystemRoles.php
<?php

declare(strict_types=1);

namespace App\Roles;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('system')]
final class SystemRoles
{
    #[Label('Administrator'), Description('Full system access')]
    public const string ADMINISTRATOR = 'administrator';

    #[Label('Editor'), Description('Can edit content')]
    public const string EDITOR = 'editor';

    #[Label('Viewer'), Description('Read-only access')]
    public const string VIEWER = 'viewer';
}
```

---

## Step 8: Map Roles to Permissions

Update your `config/mandate.php`:

```php
use App\Permissions\UserPermissions;
use App\Roles\SystemRoles;

return [
    // ... other config

    'role_permissions' => [
        SystemRoles::ADMINISTRATOR => [
            UserPermissions::class, // All permissions in the class
        ],

        SystemRoles::EDITOR => [
            UserPermissions::VIEW,
            UserPermissions::CREATE,
            UserPermissions::UPDATE,
        ],

        SystemRoles::VIEWER => [
            UserPermissions::VIEW,
        ],
    ],
];
```

---

## Step 9: Sync to Database

Run the initial sync with the `--seed` flag to create permissions and roles, and assign permissions to roles:

```bash
php artisan mandate:sync --seed
```

For subsequent syncs (adding new permissions/roles without changing existing relationships):

```bash
php artisan mandate:sync
```

---

## Step 10: Create a Feature Class (Optional)

Features gate permissions and roles behind feature flags.

```php
// app/Features/ExportFeature.php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Permissions\UserPermissions;

class ExportFeature
{
    public string $name = 'export';
    public string $label = 'Export Feature';
    public ?string $description = 'Enables data export functionality';

    public function permissions(): array
    {
        return [
            UserPermissions::EXPORT,
        ];
    }

    public function roles(): array
    {
        return [];
    }

    public function resolve($user): bool
    {
        // Gate logic - e.g., only premium users
        return $user->plan === 'premium';
    }
}
```

---

## Optional: Enable Metadata Columns

To sync `set`, `label`, and `description` to the database for UI filtering:

### Publish and run migrations

```bash
php artisan vendor:publish --tag=mandate-migrations
php artisan migrate
```

### Enable in config

```php
// config/mandate.php
'sync_columns' => true, // or ['set', 'label'] for specific columns
```

### Re-sync

```bash
php artisan mandate:sync
```

---

## Verification

Test your setup:

```php
use App\Permissions\UserPermissions;
use App\Roles\SystemRoles;
use OffloadProject\Mandate\Facades\Mandate;

// Get all permissions
$permissions = Mandate::permissions()->all();

// Get all roles
$roles = Mandate::roles()->all();

// Check if a user has a permission
$canView = Mandate::can($user, UserPermissions::VIEW);

// Check if a user has a role
$isAdmin = Mandate::hasRole($user, SystemRoles::ADMINISTRATOR);
```

---

## Troubleshooting

### Permissions/roles not discovered

1. Ensure your classes have the correct attributes (`#[PermissionsSet]` or `#[RoleSet]`)
2. Verify directory paths in `config/mandate.php` match your actual directories
3. Clear config cache: `php artisan config:clear`

### Database sync issues

1. Ensure Spatie migrations have run: `php artisan migrate:status`
2. Check for duplicate permission/role names across classes
3. Verify guard names match between config and database

### Feature flags not working

1. Ensure Pennant migrations have run
2. Check that feature classes are in a directory that Laravel Hoist can discover
3. Verify the `resolve()` method returns a boolean

---

## Quick Reference

| Package | Documentation |
|---------|---------------|
| Spatie Laravel Permission | https://spatie.be/docs/laravel-permission |
| Laravel Pennant | https://laravel.com/docs/pennant |
| Laravel Hoist | https://github.com/offload-project/laravel-hoist |
| Spatie Laravel Data | https://spatie.be/docs/laravel-data |

## Next Steps

- Read the [README](../README.md) for detailed usage examples
- Explore middleware options for route protection
- Set up event listeners for sync operations
