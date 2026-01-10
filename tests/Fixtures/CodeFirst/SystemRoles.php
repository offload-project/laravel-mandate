<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\CodeFirst;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
#[Label('System Roles')]
#[Description('Core system roles')]
class SystemRoles
{
    #[Label('Administrator')]
    #[Description('Has all permissions')]
    public const ADMIN = 'admin';

    #[Label('Editor')]
    public const EDITOR = 'editor';

    public const VIEWER = 'viewer';
}
