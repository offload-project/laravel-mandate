<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define the guard for permissions or user groups in an enum.
 * Can be applied at class level (all cases) or case level (override).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Guard
{
    public function __construct(
        public string $name,
    ) {}
}
