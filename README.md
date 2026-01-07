<p align="center">
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/v/offload-project/laravel-mandate.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/offload-project/laravel-mandate/actions"><img src="https://img.shields.io/github/actions/workflow/status/offload-project/laravel-mandate/tests.yml?branch=main&style=flat-square" alt="GitHub Tests Action Status"></a>
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/dt/offload-project/laravel-mandate.svg?style=flat-square" alt="Total Downloads"></a>
</p>

# Laravel Mandate

A role-based access control (RBAC) package for Laravel with a clean, intuitive API.

## Installation

```bash
composer require offload-project/laravel-mandate
```

```bash
php artisan vendor:publish --tag=mandate-migrations
php artisan migrate
```

That's it. No configuration required for most applications.

## Quick Start

Add the trait to any Eloquent model that needs roles and permissions (User, Team, etc.):

```php
use OffloadProject\Mandate\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Create roles and permissions, then assign them:

```php
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

// Create a role with permissions
$admin = Role::create(['name' => 'admin']);
$admin->grantPermission(Permission::create(['name' => 'article:edit']));

// Assign to a user
$user->assignRole('admin');

// Check authorization
$user->hasPermission('article:edit'); // true
$user->hasRole('admin');               // true
```

---

## Usage

### Roles

```php
// Assign roles
$user->assignRole('editor');
$user->assignRole(['editor', 'moderator']);

// Remove roles
$user->removeRole('editor');

// Replace all roles
$user->syncRoles(['editor', 'moderator']);

// Check roles
$user->hasRole('admin');                       // Has this role?
$user->hasAnyRole(['admin', 'editor']);        // Has any of these?
$user->hasAllRoles(['admin', 'editor']);       // Has all of these?
$user->hasExactRoles(['editor', 'moderator']); // Has exactly these (no more, no less)?

// Get role names
$user->getRoleNames(); // Collection: ['editor', 'moderator']
```

### Permissions

```php
// Grant permissions directly to a user
$user->grantPermission('article:publish');
$user->grantPermission(['article:publish', 'article:delete']);

// Revoke permissions
$user->revokePermission('article:publish');

// Replace all direct permissions
$user->syncPermissions(['article:view', 'article:edit']);

// Check permissions (checks both direct and role-based)
$user->hasPermission('article:edit');
$user->hasAnyPermission(['article:edit', 'article:delete']);
$user->hasAllPermissions(['article:edit', 'article:delete']);

// Check only direct permissions (ignores role-based)
$user->hasDirectPermission('article:edit');

// Get all permissions
$user->getAllPermissions();    // Direct + via roles
$user->getDirectPermissions(); // Direct only
$user->getPermissionsViaRoles(); // Via roles only
```

### Assigning Permissions to Roles

```php
$role = Role::findByName('editor');

$role->grantPermission('article:edit');
$role->grantPermission(['article:edit', 'article:publish']);

$role->revokePermission('article:publish');

$role->syncPermissions(['article:view', 'article:edit']);

$role->hasPermission('article:edit'); // true
```

### Using PHP Enums

Define permissions or roles as enums for type safety:

```php
enum Permission: string
{
    case ViewArticles = 'article:view';
    case EditArticles = 'article:edit';
    case DeleteArticles = 'article:delete';
}

// Use enum values anywhere
$user->grantPermission(Permission::EditArticles);
$user->hasPermission(Permission::EditArticles); // true
```

---

## Protecting Routes

### Middleware

```php
// Single permission
Route::get('/articles', [ArticleController::class, 'index'])
    ->middleware('permission:article:view');

// Multiple permissions (user must have ANY)
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('permission:admin:access|admin:view');

// Role-based
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('role:admin');

// Role OR permission (user needs any one)
Route::get('/reports', [ReportController::class, 'index'])
    ->middleware('role_or_permission:admin|reports:view');
```

### Route Macros

Fluent syntax for route definitions:

```php
Route::get('/articles', [ArticleController::class, 'index'])
    ->permission('article:view');

Route::get('/admin', [AdminController::class, 'index'])
    ->role('admin');

Route::get('/reports', [ReportController::class, 'index'])
    ->roleOrPermission('admin|reports:view');
```

---

## Blade Directives

### Role Checks

```blade
@role('admin')
    {{-- User has admin role --}}
@endrole

@hasrole('admin')
    {{-- Alias for @role --}}
@endhasrole

@unlessrole('guest')
    {{-- User does NOT have guest role --}}
@endunlessrole

@hasanyrole('admin|editor')
    {{-- User has admin OR editor --}}
@endhasanyrole

@hasallroles(['admin', 'editor'])
    {{-- User has admin AND editor --}}
@endhasallroles

@hasexactroles(['editor', 'moderator'])
    {{-- User has exactly these roles --}}
@endhasexactroles
```

### Permission Checks

```blade
@permission('article:edit')
    <a href="/articles/edit">Edit</a>
@endpermission

@haspermission('article:edit')
    {{-- Alias for @permission --}}
@endhaspermission

@unlesspermission('article:edit')
    {{-- User does NOT have permission --}}
@endunlesspermission

@hasanypermission(['article:edit', 'article:delete'])
    {{-- User has any of these --}}
@endhasanypermission

@hasallpermissions(['article:edit', 'article:publish'])
    {{-- User has all of these --}}
@endhasallpermissions
```

---

## Fluent Authorization Builder

For complex authorization checks, use the fluent builder:

```php
use OffloadProject\Mandate\Facades\Mandate;

// Simple checks
Mandate::for($user)->can('article:edit');       // Single permission
Mandate::for($user)->is('admin');               // Single role

// Chained with OR
Mandate::for($user)
    ->hasRole('admin')
    ->orHasPermission('article:edit')
    ->check();

// Chained with AND
Mandate::for($user)
    ->hasPermission('article:view')
    ->andHasRole('editor')
    ->check();

// Multiple conditions
Mandate::for($user)
    ->hasAnyRole(['admin', 'editor'])
    ->orHasPermission('article:manage')
    ->check();

// With context (multi-tenancy)
Mandate::for($user)
    ->inContext($team)
    ->hasPermission('project:manage')
    ->check();

// Alternative endings
Mandate::for($user)->hasRole('admin')->allowed(); // Alias for check()
Mandate::for($user)->hasRole('admin')->denied();  // Inverse of check()
```

---

## Laravel Gate Integration

Mandate registers permissions with Laravel's Gate automatically:

```php
// In controllers
$this->authorize('article:edit');

// Anywhere
Gate::allows('article:edit');
Gate::denies('article:edit');

// In Blade (works alongside Mandate directives)
@can('article:edit')
    <a href="/edit">Edit</a>
@endcan
```

---

## Query Scopes

Filter models by role or permission:

```php
// Users with specific role
User::role('admin')->get();
User::role(['admin', 'editor'])->get();

// Users without specific role
User::withoutRole('banned')->get();

// Users with specific permission
User::permission('article:edit')->get();

// Users without specific permission
User::withoutPermission('admin:access')->get();
```

---

## Artisan Commands

```bash
# Create a permission
php artisan mandate:permission article:edit
php artisan mandate:permission article:edit --guard=api

# Create a role
php artisan mandate:role editor
php artisan mandate:role editor --permissions=article:edit,article:view

# Create a capability (requires capabilities.enabled = true)
php artisan mandate:capability manage-posts
php artisan mandate:capability manage-posts --permissions=post:create,post:edit

# Assign a role to a subject (user, team, etc.)
php artisan mandate:assign-role 1 admin
php artisan mandate:assign-role 1 admin --model="App\Models\Team"

# Assign a capability to a role
php artisan mandate:assign-capability editor manage-posts

# Display all roles and permissions
php artisan mandate:show

# Clear permission cache
php artisan mandate:clear-cache
```

---

## Configuration

Publish the config file for customization:

```bash
php artisan vendor:publish --tag=mandate-config
```

### Key Options

| Option                            | Default             | Description                                    |
|-----------------------------------|---------------------|------------------------------------------------|
| `models.permission`               | `Permission::class` | Custom permission model                        |
| `models.role`                     | `Role::class`       | Custom role model                              |
| `models.capability`               | `Capability::class` | Custom capability model                        |
| `cache.expiration`                | `86400` (24h)       | Cache TTL in seconds                           |
| `wildcards.enabled`               | `false`             | Enable wildcard permissions                    |
| `capabilities.enabled`            | `false`             | Enable capabilities feature                    |
| `capabilities.direct_assignment`  | `false`             | Allow direct capability-to-user assignment     |
| `context.enabled`                 | `false`             | Enable context model support (multi-tenancy)   |
| `context.global_fallback`         | `true`              | Check global when context check fails          |
| `register_gate`                   | `true`              | Register with Laravel Gate                     |
| `events`                          | `false`             | Fire events on changes                         |

### Wildcard Permissions

Enable pattern-based permission matching:

```php
// config/mandate.php
'wildcards' => [
    'enabled' => true,
],
```

```php
// Grant wildcard permission
$user->grantPermission('article:*');

// Now matches all article permissions
$user->hasPermission('article:view');   // true
$user->hasPermission('article:edit');   // true
$user->hasPermission('article:delete'); // true
```

Wildcard syntax:

- `*` matches all at that level: `article:*` matches `article:view`, `article:edit`
- Multiple parts: `article:view,edit` matches both `article:view` and `article:edit`

---

## Capabilities

Capabilities are semantic groupings of permissions that can be assigned to roles or directly to subjects. This is an optional feature that must be explicitly enabled.

### Enabling Capabilities

```php
// config/mandate.php
'capabilities' => [
    'enabled' => true,
    'direct_assignment' => false, // Allow assigning capabilities directly to users
],
```

### Creating Capabilities

```php
use OffloadProject\Mandate\Models\Capability;

// Create a capability with permissions
$capability = Capability::create(['name' => 'manage-posts']);
$capability->grantPermission(['post:create', 'post:edit', 'post:delete', 'post:publish']);

// Or create permissions on the fly
$capability = Capability::create(['name' => 'manage-users']);
$capability->grantPermission(Permission::findOrCreate('user:view'));
$capability->grantPermission(Permission::findOrCreate('user:edit'));
```

### Assigning Capabilities to Roles

```php
$role = Role::findByName('editor');

// Assign capabilities
$role->assignCapability('manage-posts');
$role->assignCapability(['manage-posts', 'manage-comments']);

// Remove capabilities
$role->removeCapability('manage-comments');

// Sync capabilities (replace all)
$role->syncCapabilities(['manage-posts']);

// Check capabilities
$role->hasCapability('manage-posts'); // true
```

### Checking Capabilities on Users

```php
// User gets capabilities through their roles
$user->assignRole('editor');

// Check capabilities
$user->hasCapability('manage-posts');
$user->hasAnyCapability(['manage-posts', 'manage-users']);
$user->hasAllCapabilities(['manage-posts', 'manage-comments']);

// Get all capabilities
$user->getAllCapabilities();        // Direct + via roles
$user->getCapabilitiesViaRoles();   // Via roles only
```

### Permission Resolution Through Capabilities

When you check if a user has a permission, Mandate checks all paths:

1. **Direct permission** - assigned directly to the user
2. **Via role** - role has the permission
3. **Via capability (through role)** - role has a capability that has the permission
4. **Via capability (direct)** - user has a capability directly (if `direct_assignment` enabled)

```php
// All of these work automatically
$user->hasPermission('post:edit');         // Checks all paths
$user->hasPermissionViaRole('post:edit');  // Checks role + role capabilities
$user->hasPermissionViaCapability('post:edit'); // Checks capabilities only
```

### Direct Capability Assignment

Enable direct assignment to allow assigning capabilities directly to users:

```php
// config/mandate.php
'capabilities' => [
    'enabled' => true,
    'direct_assignment' => true,
],
```

```php
// Assign capabilities directly to users
$user->assignCapability('manage-posts');
$user->removeCapability('manage-posts');
$user->syncCapabilities(['manage-posts', 'manage-comments']);

// Check direct capabilities
$user->hasDirectCapability('manage-posts');
$user->getAllCapabilities(); // Includes both direct and via roles
```

### Blade Directives for Capabilities

```blade
@capability('manage-posts')
    {{-- User has manage-posts capability --}}
@endcapability

@hascapability('manage-posts')
    {{-- Alias for @capability --}}
@endhascapability

@hasanycapability('manage-posts|manage-users')
    {{-- User has any of these capabilities --}}
@endhasanycapability

@hasallcapabilities(['manage-posts', 'manage-users'])
    {{-- User has all of these capabilities --}}
@endhasallcapabilities
```

### Artisan Commands for Capabilities

```bash
# Create a capability
php artisan mandate:capability manage-posts
php artisan mandate:capability manage-posts --guard=api
php artisan mandate:capability manage-posts --permissions=post:create,post:edit,post:delete

# Assign capability to a role
php artisan mandate:assign-capability editor manage-posts
php artisan mandate:assign-capability editor manage-posts --guard=api
```

---

## Context Model (Multi-Tenancy)

Context Model enables scoping roles and permissions to a specific model (like Team, Organization, or Project). This allows for resource-specific authorization in multi-tenant applications.

### Enabling Context Support

```php
// config/mandate.php
'context' => [
    'enabled' => true,
    'global_fallback' => true, // Check global permissions when context check fails
],
```

Run the context migration after enabling:

```bash
php artisan migrate
```

### Assigning Roles and Permissions with Context

Pass a context model as the second parameter:

```php
// Assign a role within a specific team
$user->assignRole('manager', $team);

// Grant permission within a specific project
$user->grantPermission('task:edit', $project);

// Assign global role (works across all contexts)
$user->assignRole('admin'); // No context = global
```

### Checking Roles and Permissions with Context

```php
// Check if user has role in specific context
$user->hasRole('manager', $team);         // true
$user->hasRole('manager', $otherTeam);    // false (if not assigned there)

// Check permission with context
$user->hasPermission('task:edit', $project);

// Check multiple roles/permissions with context
$user->hasAnyRole(['manager', 'admin'], $team);
$user->hasAllPermissions(['task:view', 'task:edit'], $project);
```

### Global Fallback

When `global_fallback` is enabled (default), checking permissions with a context will also check global permissions:

```php
// Global permission (no context)
$user->grantPermission('reports:view');

// With global fallback enabled, this returns true
$user->hasPermission('reports:view', $team);

// Disable global fallback to check only context-specific
// config: 'context.global_fallback' => false
$user->hasPermission('reports:view', $team); // false (no context-specific grant)
```

### Getting Permissions and Roles for Context

```php
// Get roles in a specific context
$user->getRolesForContext($team);         // Returns roles for this team
$user->getRoleNames($team);               // Role names in this team

// Get permissions for context
$user->getAllPermissions($team);          // Direct + via roles for this team
$user->getPermissionNames($team);         // Permission names in this team
```

### Finding Contexts

Query which contexts a user has specific roles or permissions in:

```php
// Get all teams where user is a manager
$teams = $user->getRoleContexts('manager');

// Get all projects where user can edit tasks
$projects = $user->getPermissionContexts('task:edit');
```

### Using the Mandate Facade with Context

```php
use OffloadProject\Mandate\Facades\Mandate;

// Check with context
Mandate::hasRole($user, 'manager', $team);
Mandate::hasPermission($user, 'task:edit', $project);

// Get data with context
Mandate::getRoles($user, $team);
Mandate::getPermissions($user, $project);

// Check if context is enabled
Mandate::contextEnabled(); // true/false
```

### Context Configuration Options

| Option                    | Default | Description                                      |
|---------------------------|---------|--------------------------------------------------|
| `context.enabled`         | `false` | Enable context model support                     |
| `context.global_fallback` | `true`  | Check global when context-specific check fails   |

---

## Multiple Guards

Mandate scopes roles and permissions to authentication guards:

```php
// Create role for API guard
$role = Role::create(['name' => 'api-admin', 'guard' => 'api']);

// Find role by guard
$role = Role::findByName('admin', 'api');

// Permissions respect the model's guard
$apiUser->hasPermission('api:access'); // Checks against 'api' guard
```

---

## Events

Enable events to hook into role/permission changes:

```php
// config/mandate.php
'events' => true,
```

Available events:

| Event                | Payload                          |
|----------------------|----------------------------------|
| `RoleAssigned`       | `$subject`, `$roles`             |
| `RoleRemoved`        | `$subject`, `$roles`             |
| `PermissionGranted`  | `$subject`, `$permissions`       |
| `PermissionRevoked`  | `$subject`, `$permissions`       |
| `CapabilityAssigned` | `$subject`, `$capabilities`      |
| `CapabilityRemoved`  | `$subject`, `$capabilities`      |

```php
use OffloadProject\Mandate\Events\RoleAssigned;

class SendWelcomeEmail
{
    public function handle(RoleAssigned $event): void
    {
        if (in_array('subscriber', $event->roleNames)) {
            // Send welcome email
        }
    }
}
```

---

## Exceptions

Mandate throws descriptive exceptions:

| Exception                              | When                                      |
|----------------------------------------|-------------------------------------------|
| `RoleNotFoundException`                | Role doesn't exist                        |
| `RoleAlreadyExistsException`           | Creating duplicate role                   |
| `PermissionNotFoundException`          | Permission doesn't exist                  |
| `PermissionAlreadyExistsException`     | Creating duplicate permission             |
| `CapabilityNotFoundException`          | Capability doesn't exist                  |
| `CapabilityAlreadyExistsException`     | Creating duplicate capability             |
| `GuardMismatchException`               | Permission/role guard doesn't match model |
| `UnauthorizedException`                | Middleware authorization fails            |

### UnauthorizedException Factory Methods

```php
use OffloadProject\Mandate\Exceptions\UnauthorizedException;

// Single role/permission
UnauthorizedException::forRole('admin');
UnauthorizedException::forPermission('article:edit');

// Multiple roles/permissions
UnauthorizedException::forRoles(['admin', 'editor']);
UnauthorizedException::forPermissions(['article:edit', 'article:delete']);

// Role or permission (either would satisfy)
UnauthorizedException::forRolesOrPermissions(['admin'], ['article:manage']);

// Authentication issues
UnauthorizedException::notLoggedIn();
UnauthorizedException::notEloquentModel();
```

### Customizing Exception Messages

Publish the language files to customize messages:

```bash
php artisan vendor:publish --tag=mandate-lang
```

Edit `lang/vendor/mandate/en/messages.php`:

```php
return [
    'not_logged_in' => 'Please sign in to continue.',
    'missing_permission' => 'Access denied: requires :permission.',
    'missing_permissions' => 'Access denied: requires :permissions.',
    'missing_role' => 'Access denied: requires :role role.',
    'missing_roles' => 'Access denied: requires :roles roles.',
    'missing_role_or_permission' => 'Access denied.',
];
```

**Available placeholders:**

| Placeholder    | Description                      |
|----------------|----------------------------------|
| `:permission`  | Single permission name           |
| `:permissions` | Comma-separated permission names |
| `:role`        | Single role name                 |
| `:roles`       | Comma-separated role names       |

Messages resolve from translation files first, then fall back to built-in defaults.

### Handling Authorization Failures

```php
use OffloadProject\Mandate\Exceptions\UnauthorizedException;

// In your exception handler
public function render($request, Throwable $e)
{
    if ($e instanceof UnauthorizedException) {
        // Access required roles/permissions for custom handling
        $roles = $e->requiredRoles;
        $permissions = $e->requiredPermissions;

        return response()->json([
            'error' => 'unauthorized',
            'message' => $e->getMessage(),
        ], 403);
    }
}
```

---

## Extending Models

Use custom models with UUID/ULID support or additional fields:

```php
use OffloadProject\Mandate\Models\Role as BaseRole;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends BaseRole implements RoleContract
{
    use HasUuids;

    protected $fillable = ['name', 'guard', 'description'];
}
```

```php
// config/mandate.php
'models' => [
    'role' => App\Models\Role::class,
],
```

---

## Testing

In tests, reset permissions cache between tests:

```php
use OffloadProject\Mandate\MandateRegistrar;

protected function setUp(): void
{
    parent::setUp();

    app(MandateRegistrar::class)->forgetCachedPermissions();
}
```

---

## Requirements

- PHP 8.4+
- Laravel 11.x or 12.x

## License

MIT License. See [LICENSE](LICENSE) for details.