<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define a human-readable label for a permission or user group enum case.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Label
{
    public function __construct(
        public string $value,
    ) {}
}
