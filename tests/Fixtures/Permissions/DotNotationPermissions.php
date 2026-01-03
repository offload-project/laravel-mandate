<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Permissions;

use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\PermissionsSet;

#[PermissionsSet('dotnotation')]
final class DotNotationPermissions
{
    #[Label('View Users')]
    public const string USERS_VIEW = 'users.view';

    #[Label('Create Users')]
    public const string USERS_CREATE = 'users.create';

    #[Label('Delete Users')]
    public const string USERS_DELETE = 'users.delete';

    #[Label('View Posts')]
    public const string POSTS_VIEW = 'posts.view';

    #[Label('Create Posts')]
    public const string POSTS_CREATE = 'posts.create';

    #[Label('View Reports')]
    public const string REPORTS_VIEW = 'reports.view';
}
