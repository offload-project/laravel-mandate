<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Specifies the authentication guard for a permission or role class.
 *
 * When applied to a class, all constants in that class inherit this guard.
 *
 * @example
 * #[Guard('api')]
 * final class ApiPermissions
 * {
 *     public const string VIEW = 'api.view';
 * }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Guard
{
    public function __construct(
        public string $name = 'web'
    ) {}
}
