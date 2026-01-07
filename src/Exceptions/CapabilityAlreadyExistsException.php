<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when attempting to create a duplicate capability.
 */
final class CapabilityAlreadyExistsException extends InvalidArgumentException
{
    public static function create(string $name, string $guard): self
    {
        return new self(
            "A capability named '{$name}' already exists for guard '{$guard}'. "
            .'Use findOrCreate() if you want to retrieve the existing capability.'
        );
    }
}
