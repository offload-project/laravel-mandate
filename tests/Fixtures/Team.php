<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['name'];

    protected $table = 'teams';
}
