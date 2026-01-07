<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a permission cannot be found.
 */
final class PermissionNotFoundException extends InvalidArgumentException
{
    public static function withName(string $name, ?string $guard = null): self
    {
        $guardInfo = $guard ? " for guard '{$guard}'" : '';

        return new self(
            "Permission '{$name}' not found{$guardInfo}. "
            ."Create it first with Permission::create(['name' => 'name', 'guard' => 'guard']) "
            .'or use findOrCreate().'
        );
    }

    public static function withId(int|string $id, ?string $guard = null): self
    {
        $guardInfo = $guard ? " for guard '{$guard}'" : '';

        return new self(
            "Permission with ID '{$id}' not found{$guardInfo}. "
            .'Verify the ID exists in the permissions table.'
        );
    }
}
