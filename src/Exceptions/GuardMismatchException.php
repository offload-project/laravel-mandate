<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when attempting to use a permission/role with an incompatible guard.
 */
final class GuardMismatchException extends InvalidArgumentException
{
    /**
     * Create exception for a permission guard mismatch.
     */
    public static function forPermission(string $expected, string $actual): self
    {
        return new self(
            "Permission guard mismatch. Expected guard '{$expected}', but got '{$actual}'. "
            ."Ensure the permission's guard matches the subject's guard."
        );
    }

    /**
     * Create exception for a role guard mismatch.
     */
    public static function forRole(string $expected, string $actual): self
    {
        return new self(
            "Role guard mismatch. Expected guard '{$expected}', but got '{$actual}'. "
            ."Ensure the role's guard matches the subject's guard."
        );
    }

    /**
     * Create exception when guard cannot be determined.
     */
    public static function couldNotDetermineGuard(string $class): self
    {
        return new self(
            "Could not determine guard for model '{$class}'. "
            ."Add a 'guard_name' property to your model or ensure it's configured in auth.guards."
        );
    }
}
