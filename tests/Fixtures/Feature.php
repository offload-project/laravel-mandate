<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $table = 'features';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
