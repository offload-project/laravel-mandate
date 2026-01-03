<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Roles;

use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('hierarchical')]
final class HierarchicalRoles
{
    #[Label('Basic Viewer')]
    public const string BASIC_VIEWER = 'basic-viewer';

    #[Label('Content Editor')]
    #[Inherits(self::BASIC_VIEWER)]
    public const string CONTENT_EDITOR = 'content-editor';

    #[Label('Site Administrator')]
    #[Inherits(self::CONTENT_EDITOR)]
    public const string SITE_ADMIN = 'site-admin';
}
