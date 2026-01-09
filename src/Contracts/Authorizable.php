<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contract for models that can be authorized via Mandate.
 *
 * This interface defines the authorization methods that a subject model
 * (typically User) must implement when using the HasRoles or HasPermissions traits.
 *
 * Implementing this interface enables better type safety and IDE autocompletion
 * when working with authorization in your application.
 *
 * @example
 * ```php
 * class User extends Authenticatable implements Authorizable
 * {
 *     use HasRoles;
 * }
 * ```
 */
interface Authorizable
{
    /**
     * Check if the model has a specific permission.
     *
     * @param  string|BackedEnum|Permission  $permission  The permission to check
     * @param  Model|null  $context  Optional context model for scoped checks
     * @param  bool  $bypassFeatureCheck  Skip feature access checks
     */
    public function hasPermission(string|BackedEnum|Permission $permission, ?Model $context = null, bool $bypassFeatureCheck = false): bool;

    /**
     * Check if the model has any of the given permissions.
     *
     * @param  array<string|BackedEnum|Permission>  $permissions
     * @param  Model|null  $context  Optional context model for scoped checks
     * @param  bool  $bypassFeatureCheck  Skip feature access checks
     */
    public function hasAnyPermission(array $permissions, ?Model $context = null, bool $bypassFeatureCheck = false): bool;

    /**
     * Check if the model has all of the given permissions.
     *
     * @param  array<string|BackedEnum|Permission>  $permissions
     * @param  Model|null  $context  Optional context model for scoped checks
     * @param  bool  $bypassFeatureCheck  Skip feature access checks
     */
    public function hasAllPermissions(array $permissions, ?Model $context = null, bool $bypassFeatureCheck = false): bool;

    /**
     * Check if the model has a specific role.
     *
     * @param  string|BackedEnum|Role  $role  The role to check
     * @param  Model|null  $context  Optional context model for scoped checks
     * @param  bool  $bypassFeatureCheck  Skip feature access checks
     */
    public function hasRole(string|BackedEnum|Role $role, ?Model $context = null, bool $bypassFeatureCheck = false): bool;

    /**
     * Check if the model has any of the given roles.
     *
     * @param  array<string|BackedEnum|Role>  $roles
     * @param  Model|null  $context  Optional context model for scoped checks
     * @param  bool  $bypassFeatureCheck  Skip feature access checks
     */
    public function hasAnyRole(array $roles, ?Model $context = null, bool $bypassFeatureCheck = false): bool;

    /**
     * Check if the model has all of the given roles.
     *
     * @param  array<string|BackedEnum|Role>  $roles
     * @param  Model|null  $context  Optional context model for scoped checks
     * @param  bool  $bypassFeatureCheck  Skip feature access checks
     */
    public function hasAllRoles(array $roles, ?Model $context = null, bool $bypassFeatureCheck = false): bool;

    /**
     * Get all permissions for this model (direct + via roles + via capabilities).
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, Permission>
     */
    public function getAllPermissions(?Model $context = null): Collection;

    /**
     * Get all role names for this model.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, string>
     */
    public function getRoleNames(?Model $context = null): Collection;

    /**
     * Get the guard name for this model.
     */
    public function getGuardName(): string;

    /**
     * Grant one or more permissions to this model.
     *
     * @param  string|BackedEnum|Permission|array<string|BackedEnum|Permission>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function grantPermission(string|BackedEnum|Permission|array $permissions, ?Model $context = null): static;

    /**
     * Revoke one or more permissions from this model.
     *
     * @param  string|BackedEnum|Permission|array<string|BackedEnum|Permission>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permissions
     * @return $this
     */
    public function revokePermission(string|BackedEnum|Permission|array $permissions, ?Model $context = null): static;

    /**
     * Assign one or more roles to this model.
     *
     * @param  string|BackedEnum|Role|array<string|BackedEnum|Role>  $roles
     * @param  Model|null  $context  Optional context model for scoped role assignment
     * @return $this
     */
    public function assignRole(string|BackedEnum|Role|array $roles, ?Model $context = null): static;

    /**
     * Remove one or more roles from this model.
     *
     * @param  string|BackedEnum|Role|array<string|BackedEnum|Role>  $roles
     * @param  Model|null  $context  Optional context model for scoped role removal
     * @return $this
     */
    public function removeRole(string|BackedEnum|Role|array $roles, ?Model $context = null): static;
}
