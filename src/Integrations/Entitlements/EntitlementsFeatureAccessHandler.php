<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Integrations\Entitlements;

use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Contracts\HasEntitlementType;
use OffloadProject\Mandate\Exceptions\FeatureAccessException;

/**
 * Adapter that resolves Mandate feature access through laravel-entitlements.
 *
 * Mandate's FeatureAccessHandler asks "is this feature on?" (binary), while
 * laravel-entitlements answers "does this subscriber have capacity?" (quantitative).
 * This adapter flattens the latter into the former by treating any remaining
 * capacity as access. Global activation is left to true — laravel-entitlements
 * has no notion of a global feature switch; if you need one, compose it ahead
 * of this handler or bind your own FeatureAccessHandler implementation.
 */
final class EntitlementsFeatureAccessHandler implements FeatureAccessHandler
{
    public function __construct(
        private readonly EntitlementsBridge $bridge,
    ) {}

    public function isActive(Model $feature): bool
    {
        return true;
    }

    public function hasAccess(Model $feature, Model $subject): bool
    {
        if (! $feature instanceof HasEntitlementType) {
            throw FeatureAccessException::missingEntitlementType($feature);
        }

        return $this->bridge->can($subject, $feature->entitlementType(), 1);
    }

    public function canAccess(Model $feature, Model $subject): bool
    {
        return $this->isActive($feature) && $this->hasAccess($feature, $subject);
    }
}
