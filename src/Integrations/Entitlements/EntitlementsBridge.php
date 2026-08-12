<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Integrations\Entitlements;

use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Internal seam between Mandate and laravel-entitlements.
 *
 * Exists so the handler can be unit-tested without depending on the
 * masterix21/laravel-entitlements package at test time.
 */
interface EntitlementsBridge
{
    public function can(Model $subscriber, UnitEnum $type, int $amount = 1): bool;
}
