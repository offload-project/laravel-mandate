<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Support\Collection;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Exceptions\CircularRoleInheritanceException;

/**
 * Resolves role hierarchy and computes inherited permissions.
 *
 * Uses recursive resolution with cycle detection to handle dependency order
 * and detect circular dependencies.
 */
final class RoleHierarchyResolver
{
    /** @var array<string, array<string>> Cache of resolved permissions per role */
    private array $resolvedPermissions = [];

    /** @var array<string, bool> Tracks roles currently being resolved (for cycle detection) */
    private array $resolving = [];

    /**
     * Resolve inherited permissions for all roles.
     *
     * @param  Collection<int, RoleData>  $roles
     * @return Collection<int, RoleData>
     *
     * @throws CircularRoleInheritanceException
     */
    public function resolve(Collection $roles): Collection
    {
        $this->resolvedPermissions = [];
        $this->resolving = [];

        // Build a lookup map for quick access
        $roleMap = $roles->keyBy('name');

        return $roles->map(function (RoleData $role) use ($roleMap) {
            $inheritedPermissions = $this->resolveInheritedPermissions($role, $roleMap);

            return $role->withInheritance(
                inheritedPermissions: $inheritedPermissions,
                inheritsFrom: $role->inheritsFrom,
            );
        });
    }

    /**
     * Get the full inheritance chain for a role (for debugging/display).
     *
     * @param  Collection<string, RoleData>  $roleMap
     * @return array<string> Role names in inheritance order (ancestors first)
     */
    public function getInheritanceChain(RoleData $role, Collection $roleMap): array
    {
        $chain = [];
        $visited = [];

        $this->buildChain($role, $roleMap, $chain, $visited);

        return $chain;
    }

    /**
     * Resolve inherited permissions for a single role.
     *
     * @param  Collection<string, RoleData>  $roleMap
     * @return array<string>
     *
     * @throws CircularRoleInheritanceException
     */
    private function resolveInheritedPermissions(RoleData $role, Collection $roleMap): array
    {
        // Return cached result if available
        if (isset($this->resolvedPermissions[$role->name])) {
            return $this->resolvedPermissions[$role->name];
        }

        // No parents = no inherited permissions
        if (empty($role->inheritsFrom)) {
            $this->resolvedPermissions[$role->name] = [];

            return [];
        }

        // Detect circular dependencies
        if (isset($this->resolving[$role->name])) {
            throw new CircularRoleInheritanceException(
                "Circular role inheritance detected involving role: {$role->name}"
            );
        }

        $this->resolving[$role->name] = true;

        $inherited = [];

        foreach ($role->inheritsFrom as $parentName) {
            $parent = $roleMap->get($parentName);

            if ($parent === null) {
                // Parent role not found - skip gracefully
                // This allows defining inheritance to roles that might be
                // in other packages or defined elsewhere
                continue;
            }

            // Add parent's direct permissions
            $inherited = array_merge($inherited, $parent->permissions);

            // Recursively resolve parent's inherited permissions
            $parentInherited = $this->resolveInheritedPermissions($parent, $roleMap);
            $inherited = array_merge($inherited, $parentInherited);
        }

        unset($this->resolving[$role->name]);

        // Deduplicate and cache
        $inherited = array_values(array_unique($inherited));
        $this->resolvedPermissions[$role->name] = $inherited;

        return $inherited;
    }

    /**
     * Build the inheritance chain recursively.
     *
     * @param  Collection<string, RoleData>  $roleMap
     * @param  array<string>  $chain
     * @param  array<string, bool>  $visited
     */
    private function buildChain(RoleData $role, Collection $roleMap, array &$chain, array &$visited): void
    {
        if (isset($visited[$role->name])) {
            return;
        }
        $visited[$role->name] = true;

        foreach ($role->inheritsFrom as $parentName) {
            $parent = $roleMap->get($parentName);
            if ($parent !== null) {
                $this->buildChain($parent, $roleMap, $chain, $visited);
            }
        }

        if (! in_array($role->name, $chain, true)) {
            $chain[] = $role->name;
        }
    }
}
