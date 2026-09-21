<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\CodeFirstInlineCapabilities;

use OffloadProject\Mandate\Attributes\Capability;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
#[Capability(name: 'manage-posts', label: 'Manage Posts', description: 'Create, edit, and publish posts')]
class PostPermissions
{
    #[Label('View Posts')]
    public const VIEW = 'post:view';

    #[Label('Create Posts')]
    public const CREATE = 'post:create';

    #[Label('Publish Posts')]
    #[Capability(name: 'publishing', label: 'Publishing', description: 'Publish content live')]
    public const PUBLISH = 'post:publish';
}
