<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define parent roles for permission inheritance.
 *
 * A role inherits all permissions from its parent roles (additive merge).
 * Supports multiple parent inheritance.
 *
 * Example:
 * ```php
 * #[Label('Editor')]
 * #[Inherits(SystemRoles::VIEWER)]
 * public const string EDITOR = 'editor';
 *
 * #[Label('Super Admin')]
 * #[Inherits(SystemRoles::ADMIN, BillingRoles::BILLING_ADMIN)]
 * public const string SUPER_ADMIN = 'super-admin';
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Inherits
{
    /** @var array<string> */
    public array $parents;

    public function __construct(string ...$parents)
    {
        $this->parents = $parents;
    }
}
