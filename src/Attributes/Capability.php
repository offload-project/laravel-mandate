<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Assigns a permission to a capability during sync.
 *
 * Multiple Capability attributes can be applied to assign a permission to multiple capabilities.
 *
 * @example
 * #[Capability('user-management')]
 * public const VIEW = 'user:view';
 *
 * #[Capability('user-management')]
 * #[Capability('reporting')]
 * public const EXPORT = 'user:export';
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class Capability
{
    public function __construct(
        public string $name
    ) {}
}
