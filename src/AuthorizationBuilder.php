<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Database\Eloquent\Model;

/**
 * Fluent builder for authorization checks.
 *
 * Provides a chainable API for complex permission/role checks.
 *
 * @example
 * // Single checks
 * Mandate::for($user)->can('edit-articles');
 * Mandate::for($user)->is('admin');
 *
 * // Combined with OR
 * Mandate::for($user)->hasRole('admin')->orHasPermission('edit')->check();
 *
 * // Combined with AND
 * Mandate::for($user)->hasPermission('view')->andHasRole('editor')->check();
 *
 * // With context
 * Mandate::for($user)->inContext($team)->hasRole('admin')->check();
 */
final class AuthorizationBuilder
{
    private Model $subject;

    private ?Model $context = null;

    /** @var array<int, array{type: string, operator: string, check: string, value: string|array<string>}> */
    private array $conditions = [];

    public function __construct(Model $subject)
    {
        $this->subject = $subject;
    }

    /**
     * Set the context for scoped authorization checks.
     */
    public function inContext(?Model $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Shorthand for checking a single permission.
     */
    public function can(string $permission): bool
    {
        return $this->hasPermission($permission)->check();
    }

    /**
     * Shorthand for checking a single role.
     */
    public function is(string $role): bool
    {
        return $this->hasRole($role)->check();
    }

    /**
     * Add a permission check (first condition or implicit AND).
     */
    public function hasPermission(string $permission): self
    {
        return $this->addCondition('and', 'permission', $permission);
    }

    /**
     * Add a permission check with AND.
     */
    public function andHasPermission(string $permission): self
    {
        return $this->addCondition('and', 'permission', $permission);
    }

    /**
     * Add a permission check with OR.
     */
    public function orHasPermission(string $permission): self
    {
        return $this->addCondition('or', 'permission', $permission);
    }

    /**
     * Add a role check (first condition or implicit AND).
     */
    public function hasRole(string $role): self
    {
        return $this->addCondition('and', 'role', $role);
    }

    /**
     * Add a role check with AND.
     */
    public function andHasRole(string $role): self
    {
        return $this->addCondition('and', 'role', $role);
    }

    /**
     * Add a role check with OR.
     */
    public function orHasRole(string $role): self
    {
        return $this->addCondition('or', 'role', $role);
    }

    /**
     * Add any-permission check (first condition or implicit AND).
     *
     * @param  array<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): self
    {
        return $this->addCondition('and', 'any_permission', $permissions);
    }

    /**
     * Add any-permission check with AND.
     *
     * @param  array<string>  $permissions
     */
    public function andHasAnyPermission(array $permissions): self
    {
        return $this->addCondition('and', 'any_permission', $permissions);
    }

    /**
     * Add any-permission check with OR.
     *
     * @param  array<string>  $permissions
     */
    public function orHasAnyPermission(array $permissions): self
    {
        return $this->addCondition('or', 'any_permission', $permissions);
    }

    /**
     * Add any-role check (first condition or implicit AND).
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(array $roles): self
    {
        return $this->addCondition('and', 'any_role', $roles);
    }

    /**
     * Add any-role check with AND.
     *
     * @param  array<string>  $roles
     */
    public function andHasAnyRole(array $roles): self
    {
        return $this->addCondition('and', 'any_role', $roles);
    }

    /**
     * Add any-role check with OR.
     *
     * @param  array<string>  $roles
     */
    public function orHasAnyRole(array $roles): self
    {
        return $this->addCondition('or', 'any_role', $roles);
    }

    /**
     * Add capability check (first condition or implicit AND).
     */
    public function hasCapability(string $capability): self
    {
        return $this->addCondition('and', 'capability', $capability);
    }

    /**
     * Add capability check with AND.
     */
    public function andHasCapability(string $capability): self
    {
        return $this->addCondition('and', 'capability', $capability);
    }

    /**
     * Add capability check with OR.
     */
    public function orHasCapability(string $capability): self
    {
        return $this->addCondition('or', 'capability', $capability);
    }

    /**
     * Evaluate all conditions and return the result.
     */
    public function check(): bool
    {
        if (empty($this->conditions)) {
            return false;
        }

        $result = $this->evaluateCondition($this->conditions[0]);

        for ($i = 1; $i < count($this->conditions); $i++) {
            $condition = $this->conditions[$i];
            $checkResult = $this->evaluateCondition($condition);

            if ($condition['operator'] === 'and') {
                $result = $result && $checkResult;
            } else {
                $result = $result || $checkResult;
            }
        }

        return $result;
    }

    /**
     * Alias for check() - allows natural ending of chain.
     */
    public function allowed(): bool
    {
        return $this->check();
    }

    /**
     * Inverse of check().
     */
    public function denied(): bool
    {
        return ! $this->check();
    }

    /**
     * Add a condition to the chain.
     *
     * @param  string|array<string>  $value
     */
    private function addCondition(string $operator, string $check, string|array $value): self
    {
        $this->conditions[] = [
            'type' => 'check',
            'operator' => $operator,
            'check' => $check,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Evaluate a single condition.
     *
     * @param  array{type: string, operator: string, check: string, value: string|array<string>}  $condition
     */
    private function evaluateCondition(array $condition): bool
    {
        $value = $condition['value'];

        return match ($condition['check']) {
            'permission' => is_string($value) && $this->checkPermission($value),
            'role' => is_string($value) && $this->checkRole($value),
            'any_permission' => is_array($value) && $this->checkAnyPermission($value),
            'any_role' => is_array($value) && $this->checkAnyRole($value),
            'capability' => is_string($value) && $this->checkCapability($value),
            default => false,
        };
    }

    private function checkPermission(string $permission): bool
    {
        if (! method_exists($this->subject, 'hasPermission')) {
            return false;
        }

        return $this->subject->hasPermission($permission, $this->context);
    }

    private function checkRole(string $role): bool
    {
        if (! method_exists($this->subject, 'hasRole')) {
            return false;
        }

        return $this->subject->hasRole($role, $this->context);
    }

    /**
     * @param  array<string>  $permissions
     */
    private function checkAnyPermission(array $permissions): bool
    {
        if (! method_exists($this->subject, 'hasAnyPermission')) {
            return false;
        }

        return $this->subject->hasAnyPermission($permissions, $this->context);
    }

    /**
     * @param  array<string>  $roles
     */
    private function checkAnyRole(array $roles): bool
    {
        if (! method_exists($this->subject, 'hasAnyRole')) {
            return false;
        }

        return $this->subject->hasAnyRole($roles, $this->context);
    }

    private function checkCapability(string $capability): bool
    {
        if (! config('mandate.capabilities.enabled', false)) {
            return false;
        }

        if (! method_exists($this->subject, 'hasCapability')) {
            return false;
        }

        return $this->subject->hasCapability($capability);
    }
}
