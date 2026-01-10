<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\CodeFirst;

use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

#[Guard('web')]
class ArticlePermissions
{
    #[Label('View Articles')]
    #[Description('Allows viewing articles')]
    public const VIEW = 'article:view';

    #[Label('Create Articles')]
    #[Description('Allows creating new articles')]
    public const CREATE = 'article:create';

    #[Label('Edit Articles')]
    public const EDIT = 'article:edit';

    public const DELETE = 'article:delete';
}
