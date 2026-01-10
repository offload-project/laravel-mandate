<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Specifies the context model class for a permission or role.
 *
 * Used to scope permissions/roles to a specific context (e.g., Feature, Team).
 *
 * @example
 * #[Context(ExportFeature::class)]
 * public const EXPORT = 'user:export';
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Context
{
    /**
     * @param  class-string  $modelClass  The context model class
     */
    public function __construct(
        public string $modelClass
    ) {}
}
