<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Support;

use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\FeatureContract;
use OffloadProject\Mandate\Contracts\PermissionContract;
use OffloadProject\Mandate\Contracts\RoleContract;

/**
 * Fluent API for managing permissions, roles, and features for a specific model.
 *
 * Usage:
 *   Mandate::for($user)->grantPermission('edit posts');
 *   Mandate::for($user)->assignRole('admin');
 *   Mandate::for($user)->enableFeature('new-dashboard');
 */
final class ModelScope
{
    public function __construct(
        private readonly Model $model,
    ) {}

    /**
     * Grant permissions to the model.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function grantPermission(
        string|iterable|PermissionContract $permissions,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): static {
        if (method_exists($this->model, 'grant')) {
            $this->model->grant($permissions, $scope, $contextModel);
        }

        return $this;
    }

    /**
     * Revoke permissions from the model.
     *
     * @param  string|iterable<string>|PermissionContract  $permissions
     * @return $this
     */
    public function revokePermission(string|iterable|PermissionContract $permissions): static
    {
        if (method_exists($this->model, 'revoke')) {
            $this->model->revoke($permissions);
        }

        return $this;
    }

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
    ): static {
        if (method_exists($this->model, 'assignRole')) {
            $this->model->assignRole($roles, $scope, $contextModel);
        }

        return $this;
    }

    /**
     * Unassign roles from the model.
     *
     * @param  string|iterable<string>|RoleContract  $roles
     * @return $this
     */
    public function unassignRole(string|iterable|RoleContract $roles): static
    {
        if (method_exists($this->model, 'unassignRole')) {
            $this->model->unassignRole($roles);
        }

        return $this;
    }

    /**
     * Enable features for the model.
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function enableFeature(string|iterable|FeatureContract $features): static
    {
        if (method_exists($this->model, 'enable')) {
            $this->model->enable($features);
        }

        return $this;
    }

    /**
     * Disable features for the model.
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function disableFeature(string|iterable|FeatureContract $features): static
    {
        if (method_exists($this->model, 'disable')) {
            $this->model->disable($features);
        }

        return $this;
    }

    /**
     * Forget features for the model (reset to default resolution).
     *
     * @param  string|iterable<string>|FeatureContract  $features
     * @return $this
     */
    public function forgetFeature(string|iterable|FeatureContract $features): static
    {
        if (method_exists($this->model, 'forget')) {
            $this->model->forget($features);
        }

        return $this;
    }

    /**
     * Check if the model has been granted a permission.
     */
    public function granted(
        string|PermissionContract $permission,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        if (method_exists($this->model, 'granted')) {
            return $this->model->granted($permission, $scope, $contextModel);
        }

        return false;
    }

    /**
     * Check if the model has been assigned a role.
     */
    public function assignedRole(
        string|RoleContract $role,
        ?string $scope = null,
        Model|string|null $contextModel = null,
    ): bool {
        if (method_exists($this->model, 'assignedRole')) {
            return $this->model->assignedRole($role, $scope, $contextModel);
        }

        return false;
    }

    /**
     * Check if the model has access to a feature.
     */
    public function hasAccess(string|FeatureContract $feature): bool
    {
        if (method_exists($this->model, 'hasAccess')) {
            return $this->model->hasAccess($feature);
        }

        return false;
    }

    /**
     * Get the underlying model.
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
