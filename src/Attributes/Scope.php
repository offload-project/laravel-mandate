<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define the scope for a permission or role.
 *
 * Scope allows organizing permissions and roles into logical groupings
 * like 'feature', 'team', 'tenant', etc. When synced, items with a scope
 * will have their scope column set accordingly.
 *
 * Example:
 *   #[Scope('feature')]
 *   class BetaPermissions { ... }
 *
 *   #[Scope('team')]
 *   public const MANAGE_MEMBERS = 'manage members';
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Scope
{
    /**
     * @param  string  $name  The scope name (e.g., 'feature', 'team', 'tenant')
     */
    public function __construct(
        public string $name,
    ) {}
}
