<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\CodeFirst;

use OffloadProject\Mandate\Attributes\Capability;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
#[Capability('user-management')]
class UserPermissions
{
    #[Label('View Users')]
    public const VIEW = 'user:view';

    #[Label('Edit Users')]
    public const EDIT = 'user:edit';

    #[Label('Delete Users')]
    #[Capability('admin-only')]
    public const DELETE = 'user:delete';
}
