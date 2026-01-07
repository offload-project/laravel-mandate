<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Contract for Role models.
 *
 * Implement this interface when creating a custom Role model.
 *
 * @property string $name
 * @property string $guard
 */
interface Role
{
    /**
     * Find a role by name and guard.
     *
     * @throws \OffloadProject\Mandate\Exceptions\RoleNotFoundException
     */
    public static function findByName(string $name, ?string $guard = null): self;

    /**
     * Find a role by ID and guard.
     *
     * @throws \OffloadProject\Mandate\Exceptions\RoleNotFoundException
     */
    public static function findById(int|string $id, ?string $guard = null): self;

    /**
     * Find or create a role by name and guard.
     */
    public static function findOrCreate(string $name, ?string $guard = null): self;

    /**
     * Get the permissions assigned to this role.
     */
    public function permissions(): BelongsToMany;

    /**
     * Get subjects (users, etc.) that have this role.
     */
    public function subjects(): MorphToMany;

    /**
     * Grant permission(s) to this role.
     *
     * @param  string|array<string>|Permission  $permissions
     */
    public function grantPermission(string|array|Permission $permissions): self;

    /**
     * Revoke permission(s) from this role.
     *
     * @param  string|array<string>|Permission  $permissions
     */
    public function revokePermission(string|array|Permission $permissions): self;

    /**
     * Sync permissions on this role (replace all existing).
     *
     * @param  array<string|Permission>  $permissions
     */
    public function syncPermissions(array $permissions): self;

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string|Permission $permission): bool;

    /**
     * Get the primary key for the model.
     *
     * @return mixed
     */
    public function getKey();
}
