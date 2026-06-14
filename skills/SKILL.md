---
name: Laravel Mandate
description: Conventions and APIs for the offload-project/laravel-mandate package — roles, permissions, capabilities, wildcards, multi-tenant context, feature integration, and code-first sync.
compatible_agents:
  - Claude Code
  - Cursor
tags:
  - laravel
  - php
  - rbac
  - authorization
  - permissions
  - roles
  - capabilities
  - multi-tenancy
  - feature-flags
---

## Context

`offload-project/laravel-mandate` is a Laravel 11/12/13 package (PHP 8.2+) for role-based access control. It ships:

- Three Eloquent models — `Permission`, `Role`, `Capability` — each swappable via `config('mandate.models.*')`.
- A `HasRoles` trait applied to subjects (typically `User`, but any model can be a subject — `Team`, `Service`, etc.).
- A `Mandate` facade with a fluent `Mandate::for($user)->...->check()` builder.
- Blade directives (`@role`, `@permission`, `@capability`, `@unlesspermission`, etc.) and route middleware (`permission:`, `role:`, `role_or_permission:`) with `Route::permission()`/`Route::role()` macros.
- Wildcard permissions (`article:*`, opt-in via config).
- Multi-tenant **context** support: scope roles and permissions to a model like `Team` or `Project` (opt-in).
- **Capabilities** — semantic groupings of permissions assignable to roles or directly to subjects (opt-in).
- **Feature integration** — delegate access checks to a `FeatureAccessHandler` when a Feature model is used as context.
- **Code-first** definitions: declare permissions/roles/capabilities in PHP classes with `#[Label]`, `#[Description]`, `#[Guard]`, `#[Context]`, `#[Capability]` attributes, then sync via `mandate:sync` or `Mandate::sync()`.
- TypeScript type generation via `mandate:typescript`.
- Events for lifecycle changes (`RoleAssigned`, `PermissionGranted`, `CapabilityAssigned`, `PermissionsSynced`, `MandateSynced`, etc.) — opt-in via `events => true`.
- Structured exceptions with factory methods: `UnauthorizedException::forRole(...)`, `forPermission(...)`, `forRolesOrPermissions(...)`.
- A built-in permission cache with automatic invalidation, managed by `MandateRegistrar`.

Apply this skill when working in a Laravel app that has `offload-project/laravel-mandate` in `composer.json`, or when the user asks for help with `HasRoles`, the `Mandate` facade, `Permission`/`Role`/`Capability` models, code-first definitions, the `mandate:*` Artisan commands, or RBAC flows in this package.

## Rules

### Subject trait & checks

1. Apply `HasRoles` to any model that needs roles/permissions/capabilities — `User`, `Team`, `Service`, etc. Don't reach for a separate trait per concern; `HasRoles` covers roles, permissions, and capabilities.
2. Always use the subject methods (`$user->hasPermission(...)`, `$user->hasRole(...)`) for runtime checks. Prefer them over directly querying `Permission` / `Role` tables.
3. Use `hasPermission()` for "any path counts" checks — it walks direct grants, role grants, role-capability grants, and (if enabled) direct capability grants. Reach for `hasDirectPermission()` / `hasPermissionViaRole()` / `hasPermissionViaCapability()` only when the resolution path matters (audit logs, debug, admin UIs).

### IDs, enums, and string names

4. Methods like `assignRole`, `grantPermission`, `hasPermission`, `hasRole`, `assignCapability` accept: a string name, an integer ID, a UUID/ULID string, a PHP enum (string-backed), or arrays/collections of any of those. Don't hand-resolve to IDs in calling code.
5. Use PHP enums for type safety on permissions and roles in app code (`enum Permission: string`). The package detects backed enums and uses `->value`.
6. UUID/ULID IDs are detected automatically. Don't add custom resolution.

### Roles vs permissions vs capabilities

7. Roles bundle permissions for assignment; capabilities are *semantic groupings of permissions* (opt-in). Use a capability when several permissions always travel together and you want a single name for the bundle (e.g., `manage-posts` = `post:create|edit|delete|publish`). Don't use capabilities just because a role has many permissions — keep it semantic.
8. Capabilities require `config('mandate.capabilities.enabled')` to be true and the capability migration to have been published. Direct subject→capability assignment additionally requires `capabilities.direct_assignment => true`.

### Multi-tenancy (Context)

9. To scope a role/permission to a tenant, pass the tenant model as the second argument: `$user->assignRole('manager', $team)`, `$user->hasPermission('task:edit', $project)`. Don't introduce a parallel tenant-scoped table.
10. Context support requires `config('mandate.context.enabled')` to be true. `context.global_fallback => true` (default) means global grants still satisfy contextual checks; flip it off only when you genuinely need strict tenant isolation.
11. To query "which tenants does this user have role X in?", use `$user->getRoleContexts('manager')` / `$user->getPermissionContexts('task:edit')` rather than scanning role assignments manually.

### Feature integration

12. Feature integration is opt-in (`config('mandate.features.enabled')` + listed model classes in `features.models` + context enabled). When a model class in `features.models` is passed as the context, the bound `FeatureAccessHandler` runs first; if it denies access the role/permission check returns false without evaluation.
13. Bind exactly one `FeatureAccessHandler` in a service provider. For admin paths that must bypass the feature gate, pass `bypassFeatureCheck: true` to `hasPermission()` / `hasRole()` — do **not** rebind the handler temporarily.
14. `features.on_missing_handler` controls behavior when no handler is bound: `'deny'` (default, fail closed), `'allow'`, or `'throw'`. Keep production on `'deny'` unless you have a specific reason.

### Route protection

15. Use the route middleware names (`permission:`, `role:`, `role_or_permission:`) or the `Route::permission(...)`, `Route::role(...)`, `Route::roleOrPermission(...)` macros. Don't write per-route closures duplicating Mandate logic.
16. Multiple values in middleware strings are pipe-separated (`permission:article:edit|article:delete`) — semantics are **OR**. For AND, chain multiple middleware calls.

### Wildcards

17. Wildcard permissions (`article:*`, `*.edit`) require `config('mandate.wildcards.enabled')`. Don't assume they work out of the box.
18. Granting `article:*` covers all `article:...` permissions at check time, but only existing rows can be assigned individually. Prefer wildcards on permissions you genuinely want to "always include new ones" (e.g., super-admin), not as a shortcut for laziness.

### Code-first & sync

19. Define permissions/roles/capabilities as PHP classes with public string constants when you want them version-controlled. Use `#[Guard('web')]` on the class, `#[Label]` / `#[Description]` on class or constant, `#[Context(SomeModel::class)]` to scope a constant to a context model class, `#[Capability('manage-posts')]` to wire a permission into a capability.
20. Run `php artisan mandate:sync` (or `Mandate::sync()` in a seeder) to push code-first definitions into the database. The sync is **additive only** — it never deletes. To remove a definition you must delete the row manually.
21. Use `assignments` in `config/mandate.php` to declare role → permission/capability wiring (works with or without code-first). Reference whole classes (`ArticlePermissions::class`) to keep assignments DRY, or `'*'` for super-admin "everything".
22. Sync with `--seed` (or `Mandate::sync(seed: true)`) to apply assignments. With code-first enabled, this syncs definitions first, then seeds. Without code-first, only seeding runs.

### Models & extension

23. To customize a model (e.g., add UUID support, extra columns), extend the base model **and** implement the matching contract (`Permission as PermissionContract`, etc.), then point `config('mandate.models.permission')` (or `role`/`capability`) at your class.
24. When extending, override `$fillable` to include any added columns. The base models already declare `name`, `guard`, `label`, `description`, plus `context_type` / `context_id` on `Permission` and `Role`.
25. Don't override `Permission::create()` / `Role::create()` etc. to bypass the duplicate-check — the existing methods throw `*AlreadyExistsException` for a reason; catch the exception or use `findOrCreate()` instead.

### Cache

26. Permission lookups are cached via `MandateRegistrar`. Cache is invalidated automatically on permission/role/capability writes. In tests, call `app(MandateRegistrar::class)->forgetCachedPermissions()` in `setUp()`.
27. Tune cache lifetime via `config('mandate.cache.expiration')` (seconds; default 86400 = 24h). Set to `0` only if you genuinely cannot tolerate any cache.

### Exceptions

28. Handle middleware authorization failures by catching `UnauthorizedException` in your exception handler and reading `$e->requiredRoles` / `$e->requiredPermissions` to render a specific response.
29. Customize message strings via `lang/vendor/mandate/{locale}/messages.php` after publishing — do **not** edit the package's lang files.
30. Use the factory methods on `UnauthorizedException` (`forRole`, `forPermission`, `forRoles`, `forPermissions`, `forRolesOrPermissions`, `notLoggedIn`, `notEloquentModel`) instead of `new UnauthorizedException('...')` so the typed metadata is populated.

### Guards

31. Roles and permissions are scoped to a Laravel auth guard. Don't mix-and-match across guards — checking a `web`-guarded permission on an `api`-authenticated user will throw `GuardMismatchException`. Pass `--guard=api` to Artisan commands and `'guard' => 'api'` when creating models for non-default guards.

## Examples

### Basic setup

```php
// app/Models/User.php
use OffloadProject\Mandate\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Creating & assigning

```php
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

$admin = Role::create(['name' => 'admin']);
$admin->grantPermission(Permission::create(['name' => 'article:edit']));

$user->assignRole('admin');

$user->hasPermission('article:edit'); // true
$user->hasRole('admin');               // true
```

### Enums

```php
enum AppPermission: string
{
    case ViewArticles = 'article:view';
    case EditArticles = 'article:edit';
}

$user->grantPermission(AppPermission::EditArticles);
$user->hasPermission(AppPermission::EditArticles); // true
```

### Multi-tenant (Context)

```php
// config/mandate.php — context.enabled => true
$user->assignRole('manager', $team);
$user->grantPermission('task:edit', $project);

$user->hasRole('manager', $team);             // true
$user->hasPermission('task:edit', $project);  // true

$teams = $user->getRoleContexts('manager');   // tenants where they're a manager
```

### Capabilities

```php
// config/mandate.php — capabilities.enabled => true
$cap = Capability::create(['name' => 'manage-posts']);
$cap->grantPermission(['post:create', 'post:edit', 'post:delete', 'post:publish']);

$editor = Role::findByName('editor');
$editor->assignCapability('manage-posts');

$user->assignRole('editor');
$user->hasCapability('manage-posts');   // true
$user->hasPermission('post:edit');      // true (resolved via capability)
```

### Fluent builder

```php
use OffloadProject\Mandate\Facades\Mandate;

Mandate::for($user)
    ->hasAnyRole(['admin', 'editor'])
    ->orHasPermission('article:manage')
    ->check();

Mandate::for($user)
    ->inContext($team)
    ->hasPermission('project:manage')
    ->check();
```

### Routes

```php
Route::get('/articles', [ArticleController::class, 'index'])
    ->middleware('permission:article:view');

Route::get('/admin', [AdminController::class, 'index'])
    ->role('admin');

Route::get('/reports', [ReportController::class, 'index'])
    ->roleOrPermission('admin|report:view');
```

### Blade

```blade
@permission('article:edit')
    <a href="/articles/edit">Edit</a>
@endpermission

@hasanyrole('admin|editor')
    <a href="/admin">Admin</a>
@endhasanyrole

@capability('manage-posts')
    <button>New Post</button>
@endcapability
```

### Code-first definitions

```php
namespace App\Permissions;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
class ArticlePermissions
{
    #[Label('View Articles')]
    public const VIEW = 'article:view';

    #[Label('Edit Articles')]
    #[Description('Edit existing articles')]
    public const EDIT = 'article:edit';
}
```

```bash
php artisan mandate:sync          # sync definitions
php artisan mandate:sync --seed   # sync + apply assignments from config
```

```php
// config/mandate.php
use App\Permissions\ArticlePermissions;
use App\Roles\SystemRoles;

'assignments' => [
    SystemRoles::ADMIN => [
        'permissions' => ['*'],
    ],
    SystemRoles::EDITOR => [
        'permissions' => [ArticlePermissions::class],
    ],
],
```

### Feature integration

```php
use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;

class FlaggedFeatureHandler implements FeatureAccessHandler
{
    public function isActive(Model $feature): bool
    {
        return $feature->is_active;
    }

    public function hasAccess(Model $feature, Model $subject): bool
    {
        return $feature->subjects()->whereKey($subject->getKey())->exists();
    }

    public function canAccess(Model $feature, Model $subject): bool
    {
        return $this->isActive($feature) && $this->hasAccess($feature, $subject);
    }
}

// Register
$this->app->bind(FeatureAccessHandler::class, FlaggedFeatureHandler::class);

// Use as a context — gating runs automatically
$user->hasPermission('edit', $feature);
$user->hasPermission('edit', $feature, bypassFeatureCheck: true); // admin path
```

### Custom model (UUID)

```php
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Models\Role as BaseRole;

class Role extends BaseRole implements RoleContract
{
    protected $fillable = ['name', 'guard', 'label', 'description', 'team_id'];
}

// config/mandate.php
'models' => [
    'role' => App\Models\Role::class,
],
'model_id_type' => 'uuid',
```

### Handling authorization failures

```php
use OffloadProject\Mandate\Exceptions\UnauthorizedException;

public function render($request, Throwable $e)
{
    if ($e instanceof UnauthorizedException) {
        return response()->json([
            'error' => 'unauthorized',
            'message' => $e->getMessage(),
            'required_roles' => $e->requiredRoles,
            'required_permissions' => $e->requiredPermissions,
        ], 403);
    }

    return parent::render($request, $e);
}
```

## Anti-patterns

- ❌ Querying `permissions` / `roles` tables directly to check authorization. Use `$user->hasPermission(...)` / `$user->hasRole(...)`.
- ❌ Resolving names to IDs manually before calling `assignRole($id)` / `grantPermission($id)`. The methods take any of name, ID, UUID/ULID, or enum.
- ❌ Catching `\Throwable` around `assignRole` / `grantPermission`. Catch typed exceptions (`RoleNotFoundException`, `PermissionAlreadyExistsException`, `GuardMismatchException`) for specific handling.
- ❌ Using `new UnauthorizedException('...')` directly. Use the factory methods (`forRole`, `forPermission`, `forRolesOrPermissions`, `notLoggedIn`, ...) so `$requiredRoles` / `$requiredPermissions` are populated.
- ❌ Editing files inside `vendor/offload-project/laravel-mandate`. Extension points: custom models via `config('mandate.models.*')`, custom messages via published lang files, custom `FeatureAccessHandler` via service-container binding, custom stubs via `vendor:publish --tag=mandate-stubs`.
- ❌ Editing previously-published migration files. Add a new migration if a schema change is needed.
- ❌ Reaching for capabilities as "roles with extra steps". Capabilities are semantic permission bundles — if a role just has a few permissions, leave them on the role.
- ❌ Enabling `context.global_fallback => false` without reason. The default `true` keeps global admin grants working in tenant contexts; turning it off introduces hard-to-debug "admin can't do anything in this tenant" bugs.
- ❌ Disabling the permission cache (`cache.expiration => 0`) as a debugging shortcut. Use `php artisan mandate:cache-clear` instead.
- ❌ Migrating from Spatie Permission by hand. Use `php artisan mandate:upgrade-from-spatie --dry-run` to preview, then run without `--dry-run`. Pass `--convert-permission-sets` if upgrading from Mandate 1.x with `#[PermissionsSet]`.
- ❌ Subclassing exception classes (`UnauthorizedException`, `*NotFoundException`). Catch them and rethrow your own, or use the factory metadata.

## References

- Repository: <https://github.com/offload-project/laravel-mandate>
- README (full API & configuration): <https://github.com/offload-project/laravel-mandate/blob/main/README.md>
- Upgrade guide: <https://github.com/offload-project/laravel-mandate/blob/main/UPGRADE.md>
- Changelog: <https://github.com/offload-project/laravel-mandate/blob/main/CHANGELOG.md>
