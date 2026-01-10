<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Defines a human-readable label for a permission, role, or capability.
 *
 * Can be applied to a class (default for all constants) or individual constants.
 *
 * @example
 * #[Label('View Users')]
 * public const VIEW = 'user:view';
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Label
{
    public function __construct(
        public string $value
    ) {}
}
