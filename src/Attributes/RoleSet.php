<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define the set name for all roles in a class.
 * Used for organizing roles in the UI.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RoleSet
{
    public function __construct(
        public string $name,
        public ?string $label = null,
    ) {}
}
