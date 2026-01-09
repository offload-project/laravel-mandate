<p align="center">
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/v/offload-project/laravel-mandate.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/offload-project/laravel-mandate/actions"><img src="https://img.shields.io/github/actions/workflow/status/offload-project/laravel-mandate/tests.yml?branch=main&style=flat-square" alt="GitHub Tests Action Status"></a>
    <a href="https://packagist.org/packages/offload-project/laravel-mandate"><img src="https://img.shields.io/packagist/dt/offload-project/laravel-mandate.svg?style=flat-square" alt="Total Downloads"></a>
</p>

# Laravel Mandate

A role-based access control (RBAC) package for Laravel with a clean, intuitive API.

## Features

- **Roles & Permissions** — Assign roles to users, grant permissions to roles or directly to users
- **Capabilities** — Group permissions into semantic capabilities for cleaner authorization logic
- **Multi-Tenancy** — Scope roles and permissions to context models (Team, Organization, Project)
- **Feature Integration** — Delegate feature access checks to external packages (Flagged, etc.)
- **Wildcard Permissions** — Pattern matching with `article:*` or `*.edit` syntax
- **Multiple Guards** — Scope authorization to different authentication guards
- **Laravel Gate** — Automatic registration with Laravel's authorization system
- **Blade Directives** — `@role`, `@permission`, `@capability`, and more
- **Route Middleware** — Protect routes with `permission:`, `role:`, or `role_or_permission:`
- **Fluent Builder** — Expressive chained authorization checks
- **Query Scopes** — Filter models by role or permission
- **UUID/ULID Support** — Use any primary key type for all models
- **Caching** — Built-in permission caching with automatic invalidation
- **Events** — Hook into role, permission, and capability changes
- **Artisan Commands** — Create and manage roles, permissions, and capabilities from CLI
- **Code-First Definitions** — Define permissions, roles, and capabilities in PHP classes with attributes

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage](#usage)
    - [Roles](#roles)
    - [Permissions](#permissions)
    - [Assigning Permissions to Roles](#assigning-permissions-to-roles)
    - [Using PHP Enums](#using-php-enums)
- [Protecting Routes](#protecting-routes)
- [Blade Directives](#blade-directives)
- [Fluent Authorization Builder](#fluent-authorization-builder)
- [Laravel Gate Integration](#laravel-gate-integration)
- [Query Scopes](#query-scopes)
- [Artisan Commands](#artisan-commands)
- [Configuration](#configuration)
    - [UUID / ULID Primary Keys](#uuid--ulid-primary-keys)
    - [Custom Column Names](#custom-column-names)
    - [Wildcard Permissions](#wildcard-permissions)
- [Capabilities](#capabilities)
- [Context Model (Multi-Tenancy)](#context-model-multi-tenancy)
- [Feature Integration](#feature-integration)
- [Code-First Definitions](#code-first-definitions)
- [Multiple Guards](#multiple-guards)
- [Events](#events)
- [Exceptions](#exceptions)
- [Extending Models](#extending-models)
- [Testing](#testing)
- [Upgrading from 1.x](#upgrading-from-1x)
- [Requirements](#requirements)
- [License](#license)

## Installation

```bash
composer require offload-project/laravel-mandate
```

```bash
# Core migrations (permissions, roles, pivot tables)
php artisan vendor:publish --tag=mandate-migrations
php artisan migrate
```

That's it. No configuration required for most applications.

**Optional migrations** (publish only what you need):

```bash
# Capabilities feature (semantic permission groups)
php artisan vendor:publish --tag=mandate-migrations-capabilities

# Metadata columns (label/description for permissions, roles, capabilities)
php artisan vendor:publish --tag=mandate-migrations-meta
```

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
    ->middleware('role_or_permission:admin|report:view');
```

### Route Macros

Fluent syntax for route definitions:

```php
Route::get('/articles', [ArticleController::class, 'index'])
    ->permission('article:view');

Route::get('/admin', [AdminController::class, 'index'])
    ->role('admin');

Route::get('/reports', [ReportController::class, 'index'])
    ->roleOrPermission('admin|report:view');
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

# Migrate from Spatie Laravel Permission
php artisan mandate:upgrade-from-spatie --dry-run              # Preview changes
php artisan mandate:upgrade-from-spatie                        # Run migration
php artisan mandate:upgrade-from-spatie --create-capabilities  # Also create capabilities from prefixes
php artisan mandate:upgrade-from-spatie --convert-permission-sets  # Convert 1.x #[PermissionsSet] to capabilities
```

---

## Configuration

Publish the config file for customization:

```bash
php artisan vendor:publish --tag=mandate-config
```

### Key Options

| Option                            | Default             | Description                                      |
|-----------------------------------|---------------------|--------------------------------------------------|
| `model_id_type`                   | `'int'`             | Primary key type: `'int'`, `'uuid'`, or `'ulid'` |
| `models.permission`               | `Permission::class` | Custom permission model                          |
| `models.role`                     | `Role::class`       | Custom role model                                |
| `models.capability`               | `Capability::class` | Custom capability model                          |
| `cache.expiration`                | `86400` (24h)       | Cache TTL in seconds                             |
| `wildcards.enabled`               | `false`             | Enable wildcard permissions                      |
| `capabilities.enabled`            | `false`             | Enable capabilities feature                      |
| `capabilities.direct_assignment`  | `false`             | Allow direct capability-to-user assignment       |
| `context.enabled`                 | `false`             | Enable context model support (multi-tenancy)     |
| `context.global_fallback`         | `true`              | Check global when context check fails            |
| `features.enabled`                | `false`             | Enable feature integration                       |
| `features.models`                 | `[]`                | Model classes considered Feature contexts        |
| `features.on_missing_handler`     | `'deny'`            | Behavior when handler is not bound               |
| `register_gate`                   | `true`              | Register with Laravel Gate                       |
| `events`                          | `false`             | Fire events on changes                           |
| `column_names.subject_morph_name` | `'subject'`         | Base name for subject morph columns              |
| `column_names.context_morph_name` | `'context'`         | Base name for context morph columns              |

### UUID / ULID Primary Keys

Mandate supports UUID or ULID primary keys for all its models. Configure before running migrations:

```php
// config/mandate.php
'model_id_type' => 'uuid', // or 'ulid', default is 'int'
```

This affects:

- `permissions`, `roles`, and `capabilities` tables (primary keys)
- All pivot tables (foreign keys)

```php
// With UUID enabled, IDs are automatically generated
$permission = Permission::create(['name' => 'article:edit']);
$permission->id; // "550e8400-e29b-41d4-a716-446655440000"

$role = Role::create(['name' => 'admin']);
$role->id; // "550e8400-e29b-41d4-a716-446655440001"
```

> **Note:** Set `model_id_type` before running migrations. Changing it later requires recreating the tables.

### Custom Column Names

Customize morph column names by setting the base name. Mandate automatically appends `_id` and `_type` suffixes:

```php
// config/mandate.php
'column_names' => [
    'subject_morph_name' => 'subject',  // Creates subject_id, subject_type
    'context_morph_name' => 'context',  // Creates context_id, context_type
],
```

For example, to use `user` instead of `subject`:

```php
'column_names' => [
    'subject_morph_name' => 'user',  // Creates user_id, user_type columns
],
```

This affects pivot tables (`permission_subject`, `role_subject`, `capability_subject`) and context columns on
permissions/roles tables.

> **Note:** Set column names before running migrations. Changing them later requires recreating the tables.

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

Capabilities are semantic groupings of permissions that can be assigned to roles or directly to subjects. This is an
optional feature that must be explicitly enabled.

### Enabling Capabilities

First, publish and run the capability migrations:

```bash
php artisan vendor:publish --tag=mandate-migrations-capabilities
php artisan migrate
```

Then enable in config:

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
$capability->grantPermission(Permission::findOrCreate(''user:view'));
$capability->grantPermission(Permission::findOrCreate(''user:edit'));
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

Enable direct assignment to allow assigning capabilities directly to user:

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

Context Model enables scoping roles and permissions to a specific model (like Team, Organization, or Project). This
allows for resource-specific authorization in multi-tenant applications.

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
$user->grantPermission('report:view');

// With global fallback enabled, this returns true
$user->hasPermission('report:view', $team);

// Disable global fallback to check only context-specific
// config: 'context.global_fallback' => false
$user->hasPermission('report:view', $team); // false (no context-specific grant)
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

| Option                    | Default | Description                                    |
|---------------------------|---------|------------------------------------------------|
| `context.enabled`         | `false` | Enable context model support                   |
| `context.global_fallback` | `true`  | Check global when context-specific check fails |

---

## Feature Integration

Feature Integration enables Mandate to delegate feature access checks to an external package (like Flagged) when a
Feature model is used as a context. This allows combining feature flags with permission checks.

### How It Works

When you check a permission or role with a Feature model as the context, Mandate first verifies the subject can access
the feature before evaluating permissions. This ensures users only get permissions for features they have access to.

### Enabling Feature Integration

Feature integration requires context support to be enabled:

```php
// config/mandate.php
'context' => [
    'enabled' => true,
],

'features' => [
    'enabled' => true,
    'models' => [
        App\Models\Feature::class,
    ],
    'on_missing_handler' => 'deny', // 'allow', 'deny', or 'throw'
],
```

### Implementing the Feature Access Handler

Your feature management package must implement the `FeatureAccessHandler` contract:

```php
use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;

class FlaggedFeatureHandler implements FeatureAccessHandler
{
    public function isActive(Model $feature): bool
    {
        // Check if feature is globally active
        return $feature->is_active;
    }

    public function hasAccess(Model $feature, Model $subject): bool
    {
        // Check if subject has been granted access to the feature
        return $feature->subjects()->where('id', $subject->id)->exists();
    }

    public function canAccess(Model $feature, Model $subject): bool
    {
        // Combined check: feature must be active AND subject must have access
        return $this->isActive($feature) && $this->hasAccess($feature, $subject);
    }
}
```

Register the handler in a service provider:

```php
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;

$this->app->bind(FeatureAccessHandler::class, FlaggedFeatureHandler::class);
```

### Permission Checks with Feature Context

When you pass a Feature model as context, Mandate automatically checks feature access first:

```php
$feature = Feature::find(1);

// First checks if user can access the feature via FeatureAccessHandler
// Then checks if user has the permission within that feature context
$user->hasPermission('edit', $feature);

// Same automatic check for roles
$user->hasRole('editor', $feature);
```

If feature access is denied, the permission/role check returns `false` immediately without evaluating the actual
permission.

### Bypassing Feature Checks

For admin scenarios where you need to check permissions regardless of feature access:

```php
// Pass bypassFeatureCheck: true to skip the feature access check
$user->hasPermission('edit', $feature, bypassFeatureCheck: true);
$user->hasRole('editor', $feature, bypassFeatureCheck: true);
```

### Using the Mandate Facade

```php
use OffloadProject\Mandate\Facades\Mandate;

// Check if feature integration is enabled
Mandate::featureIntegrationEnabled();

// Check if a model is a Feature context
Mandate::isFeatureContext($model);

// Get the feature access handler
$handler = Mandate::getFeatureAccessHandler();

// Feature access checks
Mandate::isFeatureActive($feature);
Mandate::hasFeatureAccess($feature, $user);
Mandate::canAccessFeature($feature, $user);
```

### Missing Handler Behavior

Configure what happens when no `FeatureAccessHandler` is bound:

| Value   | Behavior                                   |
|---------|--------------------------------------------|
| `deny`  | Return `false` (fail closed) - **Default** |
| `allow` | Return `true` (fail open)                  |
| `throw` | Throw `FeatureAccessException`             |

```php
// config/mandate.php
'features' => [
    'on_missing_handler' => 'deny',
],
```

### Non-Feature Contexts

When checking permissions with a non-Feature context (like Team or Project), feature integration is bypassed entirely:

```php
$team = Team::find(1);

// No feature check - works like normal context
$user->hasPermission('edit', $team);
```

### Feature Configuration Options

| Option                        | Default  | Description                               |
|-------------------------------|----------|-------------------------------------------|
| `features.enabled`            | `false`  | Enable feature integration                |
| `features.models`             | `[]`     | Model classes considered Feature contexts |
| `features.on_missing_handler` | `'deny'` | Behavior when handler is not bound        |

---

## Code-First Definitions

Code-first allows you to define permissions, roles, and capabilities in PHP classes using attributes, then sync them to
the database. This provides better IDE support, version control, and type safety.

### Enabling Code-First

```php
// config/mandate.php
'code_first' => [
    'enabled' => true,
    'paths' => [
        'permissions' => app_path('Permissions'),
        'roles' => app_path('Roles'),
        'capabilities' => app_path('Capabilities'),
    ],
],
```

### Defining Permissions

Create a class with string constants for each permission:

```php
<?php

namespace App\Permissions;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
class ArticlePermissions
{
    #[Label('View Articles')]
    #[Description('Allows viewing articles')]
    public const string VIEW = 'article:view';

    #[Label('Create Articles')]
    #[Description('Allows creating new articles')]
    public const string CREATE = 'article:create';

    #[Label('Edit Articles')]
    public const string EDIT = 'article:edit';

    #[Label('Delete Articles')]
    public const string DELETE = 'article:delete';
}
```

### Defining Roles

```php
<?php

namespace App\Roles;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
class SystemRoles
{
    #[Label('Administrator')]
    #[Description('Has all permissions')]
    public const string ADMIN = 'admin';

    #[Label('Editor')]
    #[Description('Can edit content')]
    public const string EDITOR = 'editor';

    #[Label('Viewer')]
    public const string VIEWER = 'viewer';
}
```

### Available Attributes

| Attribute        | Target          | Description                                |
|------------------|-----------------|--------------------------------------------|
| `#[Guard]`       | Class           | Sets the auth guard for all constants      |
| `#[Label]`       | Class, Constant | Human-readable name                        |
| `#[Description]` | Class, Constant | Longer description                         |
| `#[Context]`     | Constant        | Context model class for scoped permissions |
| `#[Capability]`  | Constant        | Assigns permission to a capability         |

When `#[Label]` or `#[Description]` is on both the class and a constant, the constant-level attribute takes precedence.

### Syncing to Database

Use the `mandate:sync` command to create or update database records from your definitions:

```bash
# Sync all definitions
php artisan mandate:sync

# Sync only permissions
php artisan mandate:sync --permissions

# Sync only roles
php artisan mandate:sync --roles

# Sync only capabilities
php artisan mandate:sync --capabilities

# Preview changes without applying
php artisan mandate:sync --dry-run

# Sync for specific guard
php artisan mandate:sync --guard=api

# Skip confirmation in production
php artisan mandate:sync --force
```

The sync is **additive only** — it never deletes database records to prevent data loss.

### Seeding Role Assignments

Configure role-permission assignments in the config file:

```php
// config/mandate.php
'code_first' => [
    'enabled' => true,
    'assignments' => [
        'admin' => [
            'permissions' => ['article:*', 'user:*'],
            'capabilities' => ['content-management'],
        ],
        'editor' => [
            'permissions' => ['article:view', 'article:edit'],
        ],
    ],
],
```

Then sync with the `--seed` flag:

```bash
php artisan mandate:sync --seed
```

### Label and Description Columns

To store labels and descriptions in the database, publish and run the metadata migration:

```bash
php artisan vendor:publish --tag=mandate-migrations-meta
php artisan migrate
```

This adds `label` and `description` columns to the permissions, roles, and capabilities tables. These columns are useful for displaying human-readable names in admin UIs, regardless of whether you use code-first definitions.

### Generator Commands

Generate new definition classes with scaffolded constants:

```bash
# Generate a permission class with CRUD constants
php artisan mandate:make:permission ArticlePermissions
php artisan mandate:make:permission ArticlePermissions --guard=api

# Generate a role class
php artisan mandate:make:role SystemRoles

# Generate a capability class
php artisan mandate:make:capability ContentCapabilities
```

Customize the generated stubs:

```bash
php artisan vendor:publish --tag=mandate-stubs
```

### TypeScript Generation

Generate TypeScript types for frontend type safety. The command automatically merges both sources:

- **Code-first definitions** — PHP classes with attributes (if enabled)
- **Database records** — Permissions, roles, and capabilities from the database

This allows you to define permissions in code (tied to features) while managing roles in the database (business-defined).

```bash
# Generate to configured location (default: resources/js/types/mandate.ts)
php artisan mandate:typescript

# Override output path
php artisan mandate:typescript --output=resources/js/permissions.ts

# Generate only specific types
php artisan mandate:typescript --permissions
php artisan mandate:typescript --roles
```

Configure the default output path:

```php
// config/mandate.php
'code_first' => [
    'typescript_path' => resource_path('js/types/mandate.ts'),
],
```

**Grouping behavior:**

- Code-first: grouped by source class name (e.g., `ArticlePermissions`)
- Database: grouped by prefix (e.g., `article:view` → `ArticlePermissions`, `admin` → `Roles`)

Generated output (mixed sources example):

```typescript
// Auto-generated by Laravel Mandate - do not edit manually

// From code-first PHP class
export const ArticlePermissions = {
    VIEW: "article:view",
    CREATE: "article:create",
    EDIT: "article:edit",
    DELETE: "article:delete",
} as const;

// From database records (no prefix → grouped as "Roles")
export const Roles = {
    ADMIN: "admin",
    EDITOR: "editor",
    MODERATOR: "moderator",
} as const;

export type Permission = typeof ArticlePermissions[keyof typeof ArticlePermissions];
export type Role = typeof Roles[keyof typeof Roles];
```

### Using Definitions in Code

Reference your code-first constants for type-safe permission checks:

```php
use App\Permissions\ArticlePermissions;

// Type-safe permission checks (code-first)
$user->hasPermission(ArticlePermissions::EDIT);
$user->grantPermission(ArticlePermissions::VIEW);

// Database-defined roles (use string names)
$user->hasRole('admin');
$user->assignRole('editor');
```

On the frontend, use the generated TypeScript types:

```typescript
import { ArticlePermissions, Roles, type Permission, type Role } from '@/types/mandate';

// Type-safe permission checks
function canEdit(userPermissions: Permission[]): boolean {
    return userPermissions.includes(ArticlePermissions.EDIT);
}

// Type-safe role checks
function isAdmin(userRole: Role): boolean {
    return userRole === Roles.ADMIN;
}
```

### Sync Events

Listen to sync events for custom post-sync logic:

```php
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\Events\CapabilitiesSynced;
use OffloadProject\Mandate\Events\MandateSynced;

// Individual sync events
Event::listen(PermissionsSynced::class, function ($event) {
    Log::info("Synced {$event->created} new permissions, {$event->updated} updated");
});

// Aggregate event (fired after all syncs complete)
Event::listen(MandateSynced::class, function ($event) {
    // $event->permissions, $event->roles, $event->capabilities
});
```

### Code-First Configuration Options

| Option                          | Default                              | Description                              |
|---------------------------------|--------------------------------------|------------------------------------------|
| `code_first.enabled`            | `false`                              | Enable code-first mode                   |
| `code_first.paths.permissions`  | `app_path('Permissions')`            | Directory to scan for permission classes |
| `code_first.paths.roles`        | `app_path('Roles')`                  | Directory to scan for role classes       |
| `code_first.paths.capabilities` | `app_path('Capabilities')`           | Directory to scan for capability classes |
| `code_first.assignments`        | `[]`                                 | Role-permission/capability assignments   |
| `code_first.typescript_path`    | `resource_path('js/types/mandate.ts')` | Default output path for TypeScript types |
| `feature_generator`             | `null`                               | Custom feature generator class           |

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

| Event                | Payload                     |
|----------------------|-----------------------------|
| `RoleAssigned`       | `$subject`, `$roles`        |
| `RoleRemoved`        | `$subject`, `$roles`        |
| `PermissionGranted`  | `$subject`, `$permissions`  |
| `PermissionRevoked`  | `$subject`, `$permissions`  |
| `CapabilityAssigned` | `$subject`, `$capabilities` |
| `CapabilityRemoved`  | `$subject`, `$capabilities` |

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

| Exception                          | When                                      |
|------------------------------------|-------------------------------------------|
| `RoleNotFoundException`            | Role doesn't exist                        |
| `RoleAlreadyExistsException`       | Creating duplicate role                   |
| `PermissionNotFoundException`      | Permission doesn't exist                  |
| `PermissionAlreadyExistsException` | Creating duplicate permission             |
| `CapabilityNotFoundException`      | Capability doesn't exist                  |
| `CapabilityAlreadyExistsException` | Creating duplicate capability             |
| `FeatureAccessException`           | Feature handler missing (when `throw`)    |
| `GuardMismatchException`           | Permission/role guard doesn't match model |
| `UnauthorizedException`            | Middleware authorization fails            |

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

## Upgrading from 1.x

Version 2.x is a complete rewrite of Laravel Mandate. It is now a standalone RBAC package that does not depend on Spatie
Laravel Permission.

**Major changes:**

- Spatie Laravel Permission dependency removed — Mandate is now standalone
- New API — use `$user->hasPermission()` instead of `Mandate::can($user, ...)`
- `#[PermissionsSet]` → **Capabilities** (assignable permission groups)
- `#[RoleSet]` removed — use `#[Guard]` on classes instead
- Code-first is optional — disabled by default, enable via config
- New features — multi-tenancy (Context), wildcard permissions

See [UPGRADE.md](UPGRADE.md) for detailed migration instructions.

---

## Requirements

- PHP 8.4+
- Laravel 11.x or 12.x

## License

MIT License. See [LICENSE](LICENSE) for details.