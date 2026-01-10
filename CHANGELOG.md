# Changelog

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
