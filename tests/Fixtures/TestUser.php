<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

/**
 * Test user model with HasRoles trait and explicit guard.
 */
final class TestUser extends Model
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';
}
