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
    public const string VIEW = 'users.view';

    #[Label('Create Users')]
    public const string CREATE = 'users.create';

    #[Label('Update Users')]
    public const string UPDATE = 'users.update';

    #[Label('Delete Users')]
    public const string DELETE = 'users.delete';

    #[Label('Export Users'), Description('Export user data to CSV')]
    public const string EXPORT = 'users.export';
}
