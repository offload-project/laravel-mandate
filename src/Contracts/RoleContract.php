<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Contract for Role model implementations.
 */
interface RoleContract
{
    /**
     * Find a role by name and guard.
     */
    public static function findByName(string $name, ?string $guardName = null): ?self;

    /**
     * Find a role by ID.
     */
    public static function findById(int|string $id): ?self;

    /**
     * Create a new role.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createRole(array $attributes): self;

    /**
     * Get the permissions relationship.
     */
    public function permissions(): BelongsToMany;

    /**
     * Get the subjects (users, etc.) that have this role.
     */
    public function subjects(string $subjectType): MorphToMany;
}
