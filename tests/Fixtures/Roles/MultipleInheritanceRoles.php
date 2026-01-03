<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Roles;

use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Attributes\Label;
use OffloadProject\Mandate\Attributes\RoleSet;

#[RoleSet('multi')]
final class MultipleInheritanceRoles
{
    #[Label('Content Manager')]
    public const string CONTENT_MANAGER = 'content-manager';

    #[Label('Billing Admin')]
    public const string BILLING_ADMIN = 'billing-admin';

    #[Label('Super Admin')]
    #[Inherits(self::CONTENT_MANAGER, self::BILLING_ADMIN)]
    public const string SUPER_ADMIN = 'super-admin';
}
