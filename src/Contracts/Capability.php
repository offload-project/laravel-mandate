<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Contract for Capability models.
 *
 * Implement this interface when creating a custom Capability model.
 *
 * @property string $name
 * @property string $guard
 */
interface Capability
{
    /**
     * Find a capability by name and guard.
     *
     * @throws \OffloadProject\Mandate\Exceptions\CapabilityNotFoundException
     */
    public static function findByName(string $name, ?string $guard = null): self;

    /**
     * Find a capability by ID and guard.
     *
     * @throws \OffloadProject\Mandate\Exceptions\CapabilityNotFoundException
     */
    public static function findById(int|string $id, ?string $guard = null): self;

    /**
     * Find or create a capability by name and guard.
     */
    public static function findOrCreate(string $name, ?string $guard = null): self;

    /**
     * Get the permissions in this capability.
     */
    public function permissions(): BelongsToMany;

    /**
     * Get the roles that have this capability.
     */
    public function roles(): BelongsToMany;

    /**
     * Get subjects (users, etc.) that have this capability directly.
     */
    public function subjects(): MorphToMany;

    /**
     * Grant permission(s) to this capability.
     *
     * @param  string|array<string>|Permission  $permissions
     */
    public function grantPermission(string|array|Permission $permissions): self;

    /**
     * Revoke permission(s) from this capability.
     *
     * @param  string|array<string>|Permission  $permissions
     */
    public function revokePermission(string|array|Permission $permissions): self;

    /**
     * Sync permissions on this capability (replace all existing).
     *
     * @param  array<string|Permission>  $permissions
     */
    public function syncPermissions(array $permissions): self;

    /**
     * Check if the capability has a specific permission.
     */
    public function hasPermission(string|Permission $permission): bool;

    /**
     * Get the primary key for the model.
     *
     * @return mixed
     */
    public function getKey();
}
