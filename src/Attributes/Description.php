<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Defines a longer description for a permission, role, or capability.
 *
 * Can be applied to a class (default for all constants) or individual constants.
 *
 * @example
 * #[Description('Allows viewing user profiles and account details')]
 * public const string VIEW = 'user:view';
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Description
{
    public function __construct(
        public string $value
    ) {}
}
