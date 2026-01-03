<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use OffloadProject\Mandate\Concerns\HasMandateRoles;

/**
 * Test user model with HasMandateRoles trait for feature-aware authorization.
 */
final class MandateUser extends Model implements AuthorizableContract
{
    use Authorizable;
    use HasMandateRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';
}
