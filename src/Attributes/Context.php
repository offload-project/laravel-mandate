<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define the context for a permission, role, or feature.
 *
 * Context allows scoping authorization to specific contexts like teams,
 * tenants, or other domain-specific groupings.
 *
 * Example:
 *   #[Context('team')]
 *   public const VIEW_MEMBERS = 'view members';
 *
 *   #[Context('tenant', Team::class)]
 *   public const MANAGE_SETTINGS = 'manage settings';
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Context
{
    /**
     * @param  string  $name  The context name (e.g., 'team', 'tenant')
     * @param  class-string|null  $modelType  Optional model class for polymorphic context
     */
    public function __construct(
        public string $name,
        public ?string $modelType = null,
    ) {}
}
