<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown when authorization fails in middleware.
 *
 * Messages are loaded from translation files (mandate::messages.{key}).
 *
 * Placeholders supported:
 * - :permission - Single permission name
 * - :permissions - Comma-separated list of permissions
 * - :role - Single role name
 * - :roles - Comma-separated list of roles
 */
final class UnauthorizedException extends HttpException
{
    /**
     * @var array<string>
     */
    public array $requiredRoles = [];

    /**
     * @var array<string>
     */
    public array $requiredPermissions = [];

    public function __construct(
        string $message = 'Subject does not have the required authorization.',
        ?Throwable $previous = null
    ) {
        parent::__construct(403, $message, $previous);
    }

    /**
     * Create exception for a missing single permission.
     */
    public static function forPermission(string $permission): self
    {
        $exception = new self(
            trans('mandate::messages.missing_permission', ['permission' => $permission])
        );
        $exception->requiredPermissions = [$permission];

        return $exception;
    }

    /**
     * Create exception for missing permissions.
     *
     * @param  array<string>  $permissions
     */
    public static function forPermissions(array $permissions): self
    {
        if (count($permissions) === 1) {
            return self::forPermission($permissions[0]);
        }

        $exception = new self(
            trans('mandate::messages.missing_permissions', ['permissions' => implode(', ', $permissions)])
        );
        $exception->requiredPermissions = $permissions;

        return $exception;
    }

    /**
     * Create exception for a missing single role.
     */
    public static function forRole(string $role): self
    {
        $exception = new self(
            trans('mandate::messages.missing_role', ['role' => $role])
        );
        $exception->requiredRoles = [$role];

        return $exception;
    }

    /**
     * Create exception for missing roles.
     *
     * @param  array<string>  $roles
     */
    public static function forRoles(array $roles): self
    {
        if (count($roles) === 1) {
            return self::forRole($roles[0]);
        }

        $exception = new self(
            trans('mandate::messages.missing_roles', ['roles' => implode(', ', $roles)])
        );
        $exception->requiredRoles = $roles;

        return $exception;
    }

    /**
     * Create exception for missing role or permission.
     *
     * @param  array<string>  $roles
     * @param  array<string>  $permissions
     */
    public static function forRolesOrPermissions(array $roles, array $permissions): self
    {
        $exception = new self(
            trans('mandate::messages.missing_role_or_permission')
        );
        $exception->requiredRoles = $roles;
        $exception->requiredPermissions = $permissions;

        return $exception;
    }

    /**
     * Create exception when subject is not logged in.
     */
    public static function notLoggedIn(): self
    {
        return new self(
            trans('mandate::messages.not_logged_in')
        );
    }

    /**
     * Create exception when subject is not an Eloquent model.
     */
    public static function notEloquentModel(): self
    {
        return new self(
            trans('mandate::messages.not_eloquent_model')
        );
    }
}
