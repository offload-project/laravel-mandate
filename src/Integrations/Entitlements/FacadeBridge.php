<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Integrations\Entitlements;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LucaLongo\LaravelEntitlements\Contracts\EntitlementType;
use LucaLongo\LaravelEntitlements\Facades\Entitlements;
use UnitEnum;

final class FacadeBridge implements EntitlementsBridge
{
    public function can(Model $subscriber, UnitEnum $type, int $amount = 1): bool
    {
        if (! $type instanceof EntitlementType) {
            throw new InvalidArgumentException(
                'Entitlement type must implement '.EntitlementType::class.', got '.$type::class.'.'
            );
        }

        return Entitlements::can($subscriber, $type, $amount);
    }
}
