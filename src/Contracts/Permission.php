<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Contract for Permission models.
 *
 * Implement this interface when creating a custom Permission model.
 *
 * @property string $name
 * @property string $guard
 */
interface Permission
{
    /**
     * Find a permission by name and guard.
     *
     * @throws \OffloadProject\Mandate\Exceptions\PermissionNotFoundException
     */
    public static function findByName(string $name, ?string $guard = null): self;

    /**
     * Find a permission by ID and guard.
     *
     * @throws \OffloadProject\Mandate\Exceptions\PermissionNotFoundException
     */
    public static function findById(int|string $id, ?string $guard = null): self;

    /**
     * Find or create a permission by name and guard.
     */
    public static function findOrCreate(string $name, ?string $guard = null): self;

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany;

    /**
     * Get subjects (users, etc.) that have this permission directly.
     */
    public function subjects(): MorphToMany;

    /**
     * Get the primary key for the model.
     *
     * @return mixed
     */
    public function getKey();
}
