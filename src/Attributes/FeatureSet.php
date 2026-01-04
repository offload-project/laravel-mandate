<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Attributes;

use Attribute;

/**
 * Define the set name for all features in a class.
 * Used for organizing features in the UI.
 *
 * Example:
 *   #[FeatureSet('billing', 'Billing Features')]
 *   final class BillingFeatures
 *   {
 *       public const INVOICES = 'invoices';
 *       public const SUBSCRIPTIONS = 'subscriptions';
 *   }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FeatureSet
{
    public function __construct(
        public string $name,
        public ?string $label = null,
    ) {}
}
