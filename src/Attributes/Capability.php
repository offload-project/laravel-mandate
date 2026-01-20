<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Assigns a permission to a capability during sync.
 *
 * Can be applied at the class level to assign all permissions in the class to a capability,
 * or at the constant level for individual permissions. Multiple attributes can be applied
 * to assign a permission to multiple capabilities.
 *
 * @example Class-level (all permissions inherit):
 * #[Capability('user-management')]
 * class UserPermissions
 * {
 *     public const VIEW = 'user:view';
 *     public const EDIT = 'user:edit';
 * }
 * @example Constant-level:
 * #[Capability('user-management')]
 * public const VIEW = 'user:view';
 * @example Multiple capabilities:
 * #[Capability('user-management')]
 * #[Capability('reporting')]
 * public const EXPORT = 'user:export';
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class Capability
{
    public function __construct(
        public string $name
    ) {}
}
