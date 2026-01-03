<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Roles;

// Test fixture class
use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('test')]
final class TestRolesForAttribute
{
    public const string VIEWER = 'viewer';

    #[Inherits(self::VIEWER)]
    public const string EDITOR = 'editor';
}
