<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use OffloadProject\Mandate\Concerns\HasRoles;

final class User extends Authenticatable
{
    use HasRoles;

    public ?string $guard_name = null;

    protected $fillable = ['name', 'email'];

    protected $table = 'users';
}
