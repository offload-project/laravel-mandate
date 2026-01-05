<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use OffloadProject\Mandate\Concerns\HasRoles;

/**
 * Test user model with HasMandateRoles trait, explicit guard, and Laravel authorization (Authorizable).
 */
final class TestUser extends Model implements AuthorizableContract
{
    use Authorizable;
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';
}
