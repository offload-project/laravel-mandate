<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

enum FakeEntitlementType: string
{
    case AiTokens = 'ai-tokens';
    case Devices = 'devices';
}
