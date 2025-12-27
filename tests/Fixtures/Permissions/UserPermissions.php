<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Permissions;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\PermissionsSet;

#[PermissionsSet('users')]
final class UserPermissions
{
    #[Label('View Users')]
    public const string VIEW = 'view users';

    #[Label('Create Users')]
    public const string CREATE = 'create users';

    #[Label('Update Users')]
    public const string UPDATE = 'update users';

    #[Label('Delete Users')]
    public const string DELETE = 'delete users';

    #[Label('Export Users'), Description('Export user data to CSV')]
    public const string EXPORT = 'export users';
}
