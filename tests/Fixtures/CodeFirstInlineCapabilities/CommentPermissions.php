<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\CodeFirstInlineCapabilities;

use OffloadProject\Mandate\Attributes\Capability;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
class CommentPermissions
{
    #[Label('Moderate Comments')]
    #[Capability(name: 'manage-posts')]
    #[Capability(name: 'moderation', label: 'Moderation', description: 'Moderate user-generated content')]
    public const MODERATE = 'comment:moderate';
}
