<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Roles;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('system')]
final class SystemRoles
{
    #[Label('Administrator'), Description('Full system access')]
    public const string ADMIN = 'admin';

    #[Label('Editor')]
    public const string EDITOR = 'editor';

    #[Label('Viewer')]
    public const string VIEWER = 'viewer';
}
