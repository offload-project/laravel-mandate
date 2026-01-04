<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Contract for Permission model implementations.
 */
interface PermissionContract
{
    /**
     * Find a permission by name and guard.
     */
    public static function findByName(string $name, ?string $guardName = null): ?self;

    /**
     * Find a permission by ID.
     */
    public static function findById(int|string $id): ?self;

    /**
     * Create a new permission.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createPermission(array $attributes): self;

    /**
     * Get the roles relationship.
     */
    public function roles(): BelongsToMany;

    /**
     * Get the subjects (users, etc.) that have this permission directly.
     */
    public function subjects(string $subjectType): MorphToMany;
}
