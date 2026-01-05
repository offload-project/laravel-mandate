<p align="center">
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/v/offload-project/laravel-mandate.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/offload-project/laravel-mandate/actions"><img src="https://img.shields.io/github/actions/workflow/status/offload-project/laravel-mandate/tests.yml?branch=main&style=flat-square" alt="GitHub Tests Action Status"></a>
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/dt/offload-project/laravel-mandate.svg?style=flat-square" alt="Total Downloads"></a>
</p>

# Laravel Mandate

A unified authorization management system for Laravel that brings together roles, permissions, and feature flags into a
single, type-safe API.

## Features

- **Unified Authorization**: Manage roles, permissions, and feature access through a single API
- **Type-Safe**: Class-based permissions and roles using constants with PHP attributes
- **Role Hierarchy**: Define inheritance between roles - child roles automatically inherit parent permissions
- **Feature-Gated Access**: Tie permissions and roles to feature flags via Laravel Pennant
- **Auto-Discovery**: Automatically discover permission and role classes from configured directories
- **Database Sync**: Sync discovered permissions and roles to the database with events
- **Wildcard Permissions**: Support for wildcard patterns like `users.*` and `*.view`
- **Context Support**: Optional scoped permissions/roles with context models
- **Middleware**: Feature-aware route protection out of the box
- **Events**: Listen to sync operations with `PermissionsSynced`, `RolesSynced`, and `MandateSynced` events
- **TypeScript Export**: Generate TypeScript constants from your PHP permission/role classes
- **Testable**: Contracts/interfaces for all registries enable easy mocking in tests

## Requirements

- PHP 8.4+
- Laravel 11+
- [Laravel Pennant](https://laravel.com/docs/pennant) 1.0+ (for feature flag integration)

### Optional

- [Laravel Hoist](https://github.com/offload-project/laravel-hoist) 1.0+ - Enhanced feature flag management with auto-discovery

## Installation

```bash
composer require offload-project/laravel-mandate
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=mandate-config
```

Run migrations:

```bash
php artisan migrate
```

## Quick Start

Add the `HasRoles` trait to your User model:

```php
use OffloadProject\Mandate\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Define roles and permissions in config:

```php
// config/mandate-seed.php
'role_permissions' => [
    'viewer' => [
        'users.view',
        'posts.view',
    ],

    'editor' => [
        'users.view',
        'posts.view',
        'posts.create',
        'posts.update',
    ],

    'admin' => [
        'users.*',      // Wildcard: all user permissions
        'posts.*',      // Wildcard: all post permissions
    ],
],
```

Then sync to database:

```bash
php artisan mandate:sync --seed
```

Assign roles and permissions:

```php
$user->assignRole('editor');
$user->grant('posts.create');

// Check permissions
$user->granted('posts.create');  // true
$user->assignedRole('editor');   // true
```

For type-safe constants and IDE autocompletion, see [Defining Classes](#defining-roles-and-permissions-using-classes).

## Defining Roles and Permissions Using Classes

For larger applications, define permissions and roles as classes for type-safety and IDE support.

### Permission Classes

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
    public const string VIEW = 'users.view';

    #[Label('Create Users')]
    public const string CREATE = 'users.create';

    #[Label('Update Users')]
    public const string UPDATE = 'users.update';

    #[Label('Delete Users')]
    public const string DELETE = 'users.delete';

    #[Label('Export Users'), Description('Export user data to CSV')]
    public const string EXPORT = 'users.export';
}
```

### Role Classes

```bash
php artisan mandate:role SystemRoles --set=system
```

```php
// app/Roles/SystemRoles.php
use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('system')]
final class SystemRoles
{
    #[Label('Viewer')]
    public const string VIEWER = 'viewer';

    #[Label('Editor')]
    #[Inherits(self::VIEWER)]  // Editor inherits all Viewer permissions
    public const string EDITOR = 'editor';

    #[Label('Administrator'), Description('Full system access')]
    #[Inherits(self::EDITOR)]  // Admin inherits all Editor (and Viewer) permissions
    public const string ADMINISTRATOR = 'administrator';
}
```

### Map Roles to Permissions (Config)

With inheritance defined in role classes, only specify *direct* permissions - inherited permissions resolve
automatically:

```php
// config/mandate-seed.php
use App\Permissions\UserPermissions;
use App\Permissions\PostPermissions;
use App\Roles\SystemRoles;

'role_permissions' => [
    // Viewer gets base permissions
    SystemRoles::VIEWER => [
        UserPermissions::VIEW,
        PostPermissions::VIEW,
    ],

    // Editor inherits Viewer permissions, only add Editor-specific
    SystemRoles::EDITOR => [
        PostPermissions::CREATE,
        PostPermissions::UPDATE,
    ],

    // Administrator inherits Editor (and transitively Viewer)
    SystemRoles::ADMINISTRATOR => [
        UserPermissions::class,     // All user permissions
        PostPermissions::DELETE,
    ],
],
```

## Feature Gates (Optional)

Features control which permissions/roles are available. Requires [Laravel Pennant](https://laravel.com/docs/pennant):

```php
// app/Features/ExportFeature.php
use OffloadProject\Mandate\Attributes\FeatureSet;
use OffloadProject\Mandate\Attributes\Label;

#[FeatureSet('billing')]
#[Label('Export Feature')]
class ExportFeature
{
    public function resolve($user): bool
    {
        return $user->plan === 'enterprise';
    }
}
```

When a permission is gated by a feature, `granted()` returns `false` if the feature is inactive:

```php
// User has 'export users' permission but feature is disabled
$user->granted('export users');  // false - feature is inactive

// Enable the feature
Feature::for($user)->activate(ExportFeature::class);

$user->granted('export users');  // true - now granted
```

## Sync to Database

```bash
# Initial setup - seeds role permissions from config
php artisan mandate:sync --seed

# Subsequent syncs - only adds new permissions/roles, preserves DB relationships
php artisan mandate:sync
```

## Usage

### Permission and Role Methods

```php
use App\Permissions\UserPermissions;
use App\Roles\SystemRoles;

// Grant permissions
$user->grant('users.view');
$user->grant(['users.view', 'users.create']);
$user->grant(UserPermissions::VIEW);

// Check permissions (feature-aware)
$user->granted('users.view');                    // Single permission
$user->grantedAnyPermission(['users.view', 'users.create']);  // Any of these
$user->grantedAllPermissions(['users.view', 'users.create']); // All of these

// Revoke permissions
$user->revoke('users.view');
$user->revoke(['users.view', 'users.create']);

// Assign roles
$user->assignRole('editor');
$user->assignRole(['editor', 'reviewer']);
$user->assignRole(SystemRoles::EDITOR);

// Check roles
$user->assignedRole('editor');                   // Single role
$user->assignedAnyRole(['admin', 'editor']);     // Any of these
$user->assignedAllRoles(['admin', 'editor']);    // All of these

// Unassign roles
$user->unassignRole('editor');

// Get all permissions (direct + through roles)
$user->allPermissions();
$user->permissionNames();

// Get all roles
$user->allRoles();
$user->roleNames();
```

### Feature Methods

If using the `UsesFeatures` trait:

```php
use OffloadProject\Mandate\Concerns\UsesFeatures;

class User extends Authenticatable
{
    use HasRoles;
    use UsesFeatures;
}
```

```php
// Check feature access
$user->hasAccess('export-feature');
$user->enabled('export-feature');   // Alias
$user->disabled('export-feature');  // Inverse

// Multiple features
$user->hasAnyAccess(['feature-a', 'feature-b']);
$user->hasAllAccess(['feature-a', 'feature-b']);
$user->anyEnabled(['feature-a', 'feature-b']);
$user->allEnabled(['feature-a', 'feature-b']);
$user->anyDisabled(['feature-a', 'feature-b']);
$user->allDisabled(['feature-a', 'feature-b']);

// Enable/disable features (via Pennant)
$user->enable('export-feature');
$user->disable('export-feature');
$user->forget('export-feature');  // Reset to default
```

### Gate Integration

Enable Gate integration to use Laravel's standard authorization with Mandate:

```php
// config/mandate.php
'gate_integration' => true,
```

This routes Laravel's authorization through Mandate:

```php
// Permission checks (with feature flag awareness):
$user->can('users.view')
Gate::allows('users.view')
@can('users.view') // Blade
->middleware('can:users.view')

// Feature checks (by name or class):
$user->can('export')                        // By feature name
$user->can(ExportFeature::class)            // By class
@can('export') // Blade
->middleware('can:export')
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
    ->middleware('mandate.permission:users.export');

Route::get('/admin', AdminController::class)
    ->middleware('mandate.role:administrator');

Route::get('/premium', PremiumController::class)
    ->middleware('mandate.feature:App\Features\PremiumFeature');

// Multiple permissions/roles (OR logic)
Route::get('/users', UserController::class)
    ->middleware('mandate.permission:users.view,users.list');

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

### ModelScope Fluent API

Use the `Mandate` facade for a fluent API:

```php
use OffloadProject\Mandate\Facades\Mandate;

Mandate::for($user)
    ->grantPermission('users.view')
    ->assignRole('editor')
    ->enableFeature('beta-features');

Mandate::for($user)->granted('users.view');      // true
Mandate::for($user)->assignedRole('editor');     // true
Mandate::for($user)->hasAccess('beta-features'); // true
```

### Getting Data for UI

```php
use OffloadProject\Mandate\Support\MandateUI;

$ui = app(MandateUI::class);

// Get auth data for frontend
$auth = $ui->auth($user);
// Returns: ['permissions' => [...], 'roles' => [...], 'features' => [...]]

// Get permission map (for checkbox UIs)
$map = $ui->permissionsMap($user);
// Returns: ['users.view' => true, 'users.delete' => false, ...]

// Get grouped data (for admin UIs)
$grouped = $ui->grouped();
// Returns: ['permissions' => ['users' => [...]], 'roles' => [...], 'features' => [...]]
```

### Inertia Integration

Share auth data with Inertia automatically:

```php
// In your HandleInertiaRequests middleware:
use OffloadProject\Mandate\Support\MandateUI;

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => $request->user()
            ? app(MandateUI::class)->auth($request->user())
            : null,
    ]);
}
```

Or use the middleware:

```php
// In bootstrap/app.php or route middleware
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \OffloadProject\Mandate\Http\Middleware\MandateInertiaAuthShare::class,
    ]);
})
```

## Configuration

```php
// config/mandate.php
return [
    // Directories to scan for permission classes
    'discovery' => [
        'permissions' => [
            app_path('Permissions') => 'App\\Permissions',
        ],
        'roles' => [
            app_path('Roles') => 'App\\Roles',
        ],
    ],

    // Enable wildcard permission matching
    'wildcards' => true,

    // Enable Gate integration
    'gate_integration' => false,

    // TypeScript export path
    'typescript_path' => resource_path('js/permissions.ts'),
];
```

```php
// config/mandate-seed.php
return [
    // Map roles to their permissions
    'role_permissions' => [
        // SystemRoles::ADMINISTRATOR => [UserPermissions::class],
    ],
];
```

## TypeScript Export

Generate a TypeScript file containing your permissions and roles as constants:

```bash
# Generate using configured path
php artisan mandate:typescript

# Generate to a custom path
php artisan mandate:typescript --output=resources/js/auth/permissions.ts
```

### Output Format

```typescript
// This file is auto-generated by mandate:typescript. Do not edit manually.

export const UserPermissions = {
    VIEW: "users.view",
    CREATE: "users.create",
    UPDATE: "users.update",
    DELETE: "users.delete",
} as const;

export const SystemRoles = {
    VIEWER: "viewer",
    EDITOR: "editor",
    ADMINISTRATOR: "administrator",
} as const;

export const Features = {
    ExportFeature: "export",
    PremiumFeature: "premium",
} as const;

export const RoleHierarchy = {
    "editor": {
        inheritsFrom: ["viewer"],
        permissions: ["posts.create"],
        inheritedPermissions: ["users.view", "posts.view"],
    },
} as const;

export type Permission = typeof UserPermissions[keyof typeof UserPermissions];
export type Role = typeof SystemRoles[keyof typeof SystemRoles];
export type Feature = typeof Features[keyof typeof Features];
```

### Frontend Usage

```typescript
import { UserPermissions, SystemRoles } from './permissions';

// Type-safe permission checking
function canExport(userPermissions: string[]): boolean {
    return userPermissions.includes(UserPermissions.EXPORT);
}

// TypeScript catches typos at compile time
if (user.permissions.includes(UserPermissions.VIWE)) {
    // TypeScript error: Property 'VIWE' does not exist
}
```

## Role Hierarchy

Mandate supports role hierarchy with permission inheritance:

```php
use OffloadProject\Mandate\Attributes\Inherits;

#[RoleSet('system')]
final class SystemRoles
{
    public const string VIEWER = 'viewer';

    #[Inherits(self::VIEWER)]
    public const string EDITOR = 'editor';

    #[Inherits(self::EDITOR)]  // Transitively inherits VIEWER too
    public const string ADMINISTRATOR = 'administrator';
}
```

### Multiple Inheritance

```php
#[Inherits(self::CONTENT_MANAGER, self::BILLING_ADMIN)]
public const string SUPER_ADMIN = 'super-admin';
```

### How Inheritance Works

- **Additive**: Inherited permissions combine with direct permissions
- **Transitive**: If A inherits B, and B inherits C, then A gets permissions from both
- **Deduplicated**: Duplicate permissions are automatically removed
- **Circular Detection**: Circular inheritance throws `CircularRoleInheritanceException`

## Wildcard Permissions

Mandate supports wildcard patterns for flexible permission matching:

| Pattern        | Matches                         | Does Not Match           |
|----------------|---------------------------------|--------------------------|
| `users.*`      | `users.view`, `users.create`    | `posts.view`             |
| `*.view`       | `users.view`, `posts.view`      | `users.create`           |
| `users.*.view` | `users.admin.view`              | `users.view`             |

### Using Wildcards

```php
// In config - expands to all matching permissions
'role_permissions' => [
    'admin' => [
        'users.*',  // All user permissions
        '*.delete', // All delete permissions
    ],
],

// In checks
$user->granted('users.*');  // True if user has any users.* permission

// In middleware
Route::get('/users', UserController::class)
    ->middleware('mandate.permission:users.*');
```

## Attributes

### Permission Classes

| Attribute                   | Target   | Description                  |
|-----------------------------|----------|------------------------------|
| `#[PermissionsSet('name')]` | Class    | Groups permissions (required)|
| `#[Label('Human Name')]`    | Constant | Human-readable label         |
| `#[Description('Details')]` | Constant | Detailed description         |
| `#[Guard('web')]`           | Both     | Auth guard to use            |
| `#[Scope('team')]`          | Both     | Scope for context            |
| `#[Context('team', Model)]` | Both     | Context model                |

### Role Classes

| Attribute                    | Target   | Description                      |
|------------------------------|----------|----------------------------------|
| `#[RoleSet('name')]`         | Class    | Groups roles (required)          |
| `#[Label('Human Name')]`     | Constant | Human-readable label             |
| `#[Description('Details')]`  | Constant | Detailed description             |
| `#[Guard('web')]`            | Both     | Auth guard to use                |
| `#[Inherits('parent', ...)]` | Constant | Parent role(s) for inheritance   |

### Feature Classes

| Attribute                  | Target | Description                |
|----------------------------|--------|----------------------------|
| `#[FeatureSet('name')]`    | Class  | Groups features            |
| `#[Label('Human Name')]`   | Class  | Human-readable label       |

## Artisan Commands

```bash
# Create classes
php artisan mandate:permission UserPermissions --set=users
php artisan mandate:role SystemRoles --set=system
php artisan mandate:feature ExportFeature --set=billing

# Sync to database
php artisan mandate:sync                  # Creates new, preserves relationships
php artisan mandate:sync --seed           # Seeds role permissions from config
php artisan mandate:sync --permissions    # Only permissions
php artisan mandate:sync --roles          # Only roles
php artisan mandate:sync --guard=api      # Specific guard

# Generate TypeScript
php artisan mandate:typescript
php artisan mandate:typescript --output=custom.ts
```

## Events

```php
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\Events\MandateSynced;

Event::listen(PermissionsSynced::class, function (PermissionsSynced $event) {
    Log::info('Permissions synced', [
        'created' => $event->created,
        'existing' => $event->existing,
        'updated' => $event->updated,
    ]);
});

Event::listen(RolesSynced::class, function (RolesSynced $event) {
    Log::info('Roles synced', [
        'created' => $event->created,
        'permissions_synced' => $event->permissionsSynced,
        'seeded' => $event->seeded,
    ]);
});
```

## Testing

```bash
./vendor/bin/pest
```

### Mocking in Tests

```php
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;

public function test_something()
{
    $mockRegistry = Mockery::mock(PermissionRegistryContract::class);
    $mockRegistry->shouldReceive('find')->with('users.view')->andReturn($permissionData);

    $this->app->instance(PermissionRegistryContract::class, $mockRegistry);

    // Your test...
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
