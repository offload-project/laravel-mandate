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

For more details, see
the [Spatie Laravel Permission documentation](https://spatie.be/docs/laravel-permission/v6/introduction).

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

## Step 5: Install and Configure Laravel Mandate

With all dependencies in place, follow the [Installation](../README.md#installation) and [Quick Start](../README.md#quick-start) sections in the README to:

1. Install Laravel Mandate
2. Create permission and role classes
3. Map roles to permissions
4. Sync to the database
5. Optionally set up feature flags

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

| Package                   | Documentation                                    |
|---------------------------|--------------------------------------------------|
| Spatie Laravel Permission | https://spatie.be/docs/laravel-permission        |
| Laravel Pennant           | https://laravel.com/docs/pennant                 |
| Laravel Hoist             | https://github.com/offload-project/laravel-hoist |
| Spatie Laravel Data       | https://spatie.be/docs/laravel-data              |

## Next Steps

- Read the [README](../README.md) for detailed usage examples
- Explore middleware options for route protection
- Set up event listeners for sync operations
