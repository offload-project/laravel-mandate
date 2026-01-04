<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Contract for models that have roles.
 */
interface HasRolesContract extends HasPermissionsContract
{
    /**
     * Get the roles relationship.
     */
    public function roles(): MorphToMany;

    /**
     * Check if the model has been assigned a specific role.
     */
    public function assignedRole(
        string|RoleContract $role,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Check if the model has been assigned any of the given roles.
     *
     * @param  iterable<string|RoleContract>  $roles
     */
    public function assignedAnyRole(
        iterable $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Check if the model has been assigned all of the given roles.
     *
     * @param  iterable<string|RoleContract>  $roles
     */
    public function assignedAllRoles(
        iterable $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool;

    /**
     * Assign roles to the model.
     *
     * @param  string|iterable<string>|RoleContract  $roles
     * @return $this
     */
    public function assignRole(
        string|iterable|RoleContract $roles,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): static;

    /**
     * Remove roles from the model.
     *
     * @param  string|iterable<string>|RoleContract  $roles
     * @return $this
     */
    public function unassignRole(string|iterable|RoleContract $roles): static;

    /**
     * Get all role names for the model.
     *
     * @return array<string>
     */
    public function roleNames(): array;

    /**
     * Get all roles for the model.
     *
     * @return Collection<int, RoleContract>
     */
    public function allRoles(): Collection;

    /**
     * Get permissions through assigned roles.
     *
     * @return Collection<int, PermissionContract>
     */
    public function permissionsThroughRoles(): Collection;
}
