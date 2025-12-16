<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define the set name for all permissions in a class.
 * Used for organizing permissions in the UI.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PermissionsSet
{
    public function __construct(
        public string $name,
        public ?string $label = null,
    ) {}
}
