# Changelog

## [3.3.2](https://github.com/offload-project/laravel-mandate/compare/v3.3.1...v3.3.2) (2026-01-21)


### Bug Fixes

* The #[Capability('...')] attribute on permission classes/constants was being discovered but never used to create the actual capability-permission relationships. Now when syncing permissions, the command also:
  1. Finds or creates each capability referenced by #[Capability] attributes                                                                       
  2. Grants the permission to those capabilities via $capability->grantPermission($permission) ([#40](https://github.com/offload-project/laravel-mandate/issues/40)) ([a246685](https://github.com/offload-project/laravel-mandate/commit/a24668576c19f0f681a13e15ee40249b55e4ae1b))

## [3.3.1](https://github.com/offload-project/laravel-mandate/compare/v3.3.0...v3.3.1) (2026-01-21)


### Bug Fixes

* mandate:sync --seed with code-first enabled: Syncs ALL discovered permissions to DB, then seeds assignments               
* mandate:sync --seed without code-first: Only seeds assignments from config ([#38](https://github.com/offload-project/laravel-mandate/issues/38)) ([516e57d](https://github.com/offload-project/laravel-mandate/commit/516e57d9c00af08b5e8bad0f85f8550a56a1740c))

## [3.3.0](https://github.com/offload-project/laravel-mandate/compare/v3.2.0...v3.3.0) (2026-01-20)


### Features

* Allow #[Capability] to be applied to classes, and have DefinitionDiscoverer merge class-level and constant-level capabilities when building PermissionDefinitions.
* Add fixtures and unit tests to validate class-level capabilities and capability merging behavior.
* Update permission and capability stubs to use colon-delimited permission names, introduce a VIEW_ANY permission, and adjust default capability naming/labels. ([#36](https://github.com/offload-project/laravel-mandate/issues/36)) ([7b90225](https://github.com/offload-project/laravel-mandate/commit/7b9022526d03893c0120c2edad1e9b20eef7309f))


## [3.2.0](https://github.com/offload-project/laravel-mandate/compare/v3.1.0...v3.2.0) (2026-01-18)



### Features

* sync and seed method ([#33](https://github.com/offload-project/laravel-mandate/issues/33)) ([d2ba465](https://github.com/offload-project/laravel-mandate/commit/d2ba46546618b9dc8f5f0dc5ad158c54c55145ba))

## [3.1.0](https://github.com/offload-project/laravel-mandate/compare/v3.0.0...v3.1.0) (2026-01-18)


### Features

* update class gen to use code-first config paths ([#31](https://github.com/offload-project/laravel-mandate/issues/31)) ([b951179](https://github.com/offload-project/laravel-mandate/commit/b951179170633caa96148b3dbda95bae644be395))

## [3.0.0](https://github.com/offload-project/laravel-mandate/compare/v2.1.0...v3.0.0) (2026-01-17)

Refactored the role/permission assignments configuration to make it work independently of code-first mode. The assignments configuration is moved from mandate.code_first.assignments to mandate.assignments at the top level, and the --seed flag now works even when code-first is disabled.

### ⚠ BREAKING CHANGES

* use assignments to seed roles and permissions without code-fir… ([#29](https://github.com/offload-project/laravel-mandate/issues/29))

### Features

* use assignments to seed roles and permissions without code-fir… ([#29](https://github.com/offload-project/laravel-mandate/issues/29)) ([1330a65](https://github.com/offload-project/laravel-mandate/commit/1330a65f4fb7a016c21ca1bf30eff8f3d5a36270))

## [2.1.0](https://github.com/offload-project/laravel-mandate/compare/v2.0.1...v2.1.0) (2026-01-17)

Consolidates the command interface by renaming file generation commands from mandate:make:* to mandate:* and merging database creation functionality into the same commands via a --db flag. This simplifies the command structure and provides a more intuitive API where the default behavior generates PHP classes (code-first approach) and the --db flag creates database records directly (database-first approach).

### Features

* update signature for file gen commands ([#27](https://github.com/offload-project/laravel-mandate/issues/27)) ([89c1db5](https://github.com/offload-project/laravel-mandate/commit/89c1db51bea2ee76e642345c6ee38d0f37c1dd50))

## [2.0.1](https://github.com/offload-project/laravel-mandate/compare/v2.0.0...v2.0.1) (2026-01-10)


### Bug Fixes

* lower php version ([#25](https://github.com/offload-project/laravel-mandate/issues/25)) ([8451a49](https://github.com/offload-project/laravel-mandate/commit/8451a493c0f72dfb03820c27a2fed0c3d8f4ce9e))

## [2.0.0](https://github.com/offload-project/laravel-mandate/compare/v1.4.0...v2.0.0) (2026-01-09)


This release is a major rewrite of Laravel Mandate with significant new features and architectural improvements.

### Features

#### Code-First Definitions
* Define permissions, roles, and capabilities as PHP classes with attributes
* Auto-discovery and synchronization to the database via `mandate:sync`
* IDE autocompletion and type safety for authorization checks
* Support for labels, descriptions, guards, and feature associations via attributes

#### Context-Scoped Authorization (Multi-Tenancy)
* Scope roles and permissions to a polymorphic context model (Team, Organization, etc.)
* Global fallback option to check unscoped permissions when context check fails
* Full multi-tenancy support for complex authorization scenarios

#### Capabilities System
* Semantic groupings of permissions for logical organization
* Capabilities can be assigned to roles or directly to subjects
* Hierarchical permission bundling for cleaner role management

#### Feature Integration
* Integration with Laravel Pennant or Hoist for feature flag gating
* Tie permissions to feature flags for conditional access
* Configurable behavior when feature handler is missing (allow/deny/throw)

#### Audit Logging
* Pluggable `AuditLogger` contract for custom audit implementations
* Log permission grants, revokes, role assignments, and access denials
* Optional logging of all permission/role checks for compliance
* Default logger using Laravel's logging system

#### New Commands
* `mandate:health` - Check configuration and database health with `--fix` option
* `mandate:sync` - Synchronize code-first definitions to database
* `mandate:typescript` - Generate TypeScript constants for frontend
* `mandate:upgrade-from-spatie` - Migrate from Spatie Laravel Permission
* `mandate:make:permission` - Scaffold a new permission class
* `mandate:make:role` - Scaffold a new role class
* `mandate:make:capability` - Scaffold a new capability class
* `mandate:make:feature` - Scaffold a new feature class (requires feature generator)

#### Authorizable Contract
* Type-safe interface for models using Mandate authorization
* Better IDE support and method documentation
* Standardized authorization method signatures

#### Model ID Types
* Support for `int`, `uuid`, or `ulid` primary keys
* Configurable via `model_id_type` config option

#### Fluent Authorization API
* Semantic method names: `grantPermission()`, `revokePermission()`, `assignRole()`, `removeRole()`
* Context parameter support on all authorization methods
* Feature bypass option for internal checks

#### Consolidated Migrations
* Simplified migration structure with two main migration files
* Optional label/description columns migration
* Cleaner upgrade path for existing installations

### Breaking Changes

* PHP 8.4+ required
* Laravel 11+ required
* Spatie Laravel Permission dependency removed - now fully standalone
* Migration structure has changed - see UPGRADE.md for migration instructions
* Some method signatures have changed to support context parameter
* Configuration file structure updated with new sections

### Migration from v1.x

See [UPGRADE.md](UPGRADE.md) for detailed migration instructions including:
- Database migration updates
- Configuration file changes
- Code changes for new method signatures


## [1.4.0](https://github.com/offload-project/laravel-mandate/compare/v1.3.0...v1.4.0) (2026-01-03)


### Features

* add wildcard support ([#11](https://github.com/offload-project/laravel-mandate/issues/11)) ([94d66d6](https://github.com/offload-project/laravel-mandate/commit/94d66d6a9875d12092ce443f7157fde31f0d3c4a))

## [1.3.0](https://github.com/offload-project/laravel-mandate/compare/v1.2.0...v1.3.0) (2026-01-03)


### Features

* role permission inheritance ([#8](https://github.com/offload-project/laravel-mandate/issues/8)) ([1208b21](https://github.com/offload-project/laravel-mandate/commit/1208b21abfa175fd79b2f7d28de6dbdc102fb7be))

## [1.2.0](https://github.com/offload-project/laravel-mandate/compare/v1.1.0...v1.2.0) (2025-12-28)


### Features

* typescript generation for roles, permissions, and features for … ([#6](https://github.com/offload-project/laravel-mandate/issues/6)) ([00f7ebc](https://github.com/offload-project/laravel-mandate/commit/00f7ebc164d1da43fcd767ee470cbf8fb95dac00))
* typescript generation for roles, permissions, and features for use in the ui ([00f7ebc](https://github.com/offload-project/laravel-mandate/commit/00f7ebc164d1da43fcd767ee470cbf8fb95dac00))

## [1.1.0](https://github.com/offload-project/laravel-mandate/compare/v1.0.0...v1.1.0) (2025-12-27)


### Features

* update permissions stub to follow spatie permissions name patte… ([#4](https://github.com/offload-project/laravel-mandate/issues/4)) ([8013d6c](https://github.com/offload-project/laravel-mandate/commit/8013d6cb17d4c3541f5d5b1b32718c7e554836d4))

## 1.0.0 (2025-12-16)


### Miscellaneous Chores

* initial commit ([5238190](https://github.com/offload-project/laravel-mandate/commit/52381906d59964827e5983616ef8e02d111b7eaf))
