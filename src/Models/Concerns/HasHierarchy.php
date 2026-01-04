<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Models\Concerns;

use Illuminate\Support\Collection;

/**
 * Provides role hierarchy functionality.
 */
trait HasHierarchy
{
    /**
     * Get parent role names from the Inherits attribute.
     *
     * @return array<string>
     */
    public function getParentRoleNames(): array
    {
        return $this->getAttribute('inherits_from') ?? [];
    }

    /**
     * Get parent roles.
     *
     * @return Collection<int, static>
     */
    public function getParentRoles(): Collection
    {
        $parentNames = $this->getParentRoleNames();

        if (empty($parentNames)) {
            return collect();
        }

        return static::query()
            ->whereIn('name', $parentNames)
            ->where('guard_name', $this->getAttribute('guard_name'))
            ->get();
    }

    /**
     * Get child roles that inherit from this role.
     *
     * @return Collection<int, static>
     */
    public function getChildRoles(): Collection
    {
        return static::query()
            ->where('guard_name', $this->getAttribute('guard_name'))
            ->get()
            ->filter(function ($role) {
                $inheritsFrom = $role->getAttribute('inherits_from') ?? [];

                return in_array($this->getAttribute('name'), $inheritsFrom, true);
            })
            ->values();
    }

    /**
     * Get all ancestor roles (parents, grandparents, etc.).
     *
     * @param  array<string>  $visited  Tracks visited roles to prevent infinite loops
     * @return Collection<int, static>
     */
    public function getAllAncestors(array $visited = []): Collection
    {
        $ancestors = collect();
        $currentName = $this->getAttribute('name');

        if (in_array($currentName, $visited, true)) {
            return $ancestors;
        }

        $visited[] = $currentName;

        foreach ($this->getParentRoles() as $parent) {
            $ancestors->push($parent);
            $ancestors = $ancestors->merge($parent->getAllAncestors($visited));
        }

        return $ancestors->unique('id')->values();
    }

    /**
     * Get all descendant roles (children, grandchildren, etc.).
     *
     * @param  array<string>  $visited  Tracks visited roles to prevent infinite loops
     * @return Collection<int, static>
     */
    public function getAllDescendants(array $visited = []): Collection
    {
        $descendants = collect();
        $currentName = $this->getAttribute('name');

        if (in_array($currentName, $visited, true)) {
            return $descendants;
        }

        $visited[] = $currentName;

        foreach ($this->getChildRoles() as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants($visited));
        }

        return $descendants->unique('id')->values();
    }

    /**
     * Check if this role inherits from another role.
     */
    public function inheritsFrom(string|self $role): bool
    {
        $roleName = $role instanceof self ? $role->getAttribute('name') : $role;

        return $this->getAllAncestors()
            ->contains(fn ($ancestor) => $ancestor->getAttribute('name') === $roleName);
    }

    /**
     * Check if another role inherits from this role.
     */
    public function isInheritedBy(string|self $role): bool
    {
        $roleName = $role instanceof self ? $role->getAttribute('name') : $role;

        return $this->getAllDescendants()
            ->contains(fn ($descendant) => $descendant->getAttribute('name') === $roleName);
    }
}
