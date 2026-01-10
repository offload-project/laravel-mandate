<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\CodeFirst;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
class ContentCapabilities
{
    #[Label('Content Management')]
    #[Description('Manage all content')]
    public const MANAGE_CONTENT = 'content-management';

    #[Label('User Management')]
    public const MANAGE_USERS = 'user-management';
}
