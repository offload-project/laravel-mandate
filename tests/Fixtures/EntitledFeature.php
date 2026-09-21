<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use OffloadProject\Mandate\Contracts\HasEntitlementType;
use UnitEnum;

class EntitledFeature extends Feature implements HasEntitlementType
{
    protected $table = 'features';

    public function entitlementType(): UnitEnum
    {
        return FakeEntitlementType::AiTokens;
    }
}
