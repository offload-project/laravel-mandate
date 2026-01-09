<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for wildcard permission handlers.
 *
 * Implement this interface to provide custom wildcard matching logic.
 * The default implementation uses asterisk (*) as the wildcard character.
 *
 * Examples with default handler:
 * - "article:*" matches "article:view", "article:edit", "article:delete"
 * - "*.view" matches "article:view", "user:view", "post:view"
 * - "*" matches everything
 */
interface WildcardHandler
{
    /**
     * Check if a pattern matches a given permission name.
     *
     * @param  string  $pattern  The wildcard pattern (e.g., "article:*")
     * @param  string  $permission  The permission to check (e.g., "article:edit")
     */
    public function matches(string $pattern, string $permission): bool;

    /**
     * Get all permissions that match a given pattern.
     *
     * @param  string  $pattern  The wildcard pattern
     * @param  Collection<int, \OffloadProject\Mandate\Models\Permission>  $permissions  All available permissions
     * @return Collection<int, \OffloadProject\Mandate\Models\Permission>
     */
    public function getMatchingPermissions(string $pattern, Collection $permissions): Collection;

    /**
     * Check if a string contains wildcard characters.
     */
    public function containsWildcard(string $pattern): bool;
}
