<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use UnitEnum;

/**
 * Contract for Mandate feature models that map to a laravel-entitlements type.
 *
 * Implementing this on a Feature model lets the EntitlementsFeatureAccessHandler
 * delegate access checks to the laravel-entitlements package by translating the
 * Feature into its corresponding EntitlementType backed-enum case.
 */
interface HasEntitlementType
{
    /**
     * The laravel-entitlements EntitlementType case this feature maps to.
     *
     * Return a case of a backed enum that implements
     * LucaLongo\LaravelEntitlements\Contracts\EntitlementType.
     */
    public function entitlementType(): UnitEnum;
}
