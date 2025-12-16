<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define a description for a permission or user group enum case.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Description
{
    public function __construct(
        public string $value,
    ) {}
}
