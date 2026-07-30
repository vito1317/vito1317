<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Algorithm extends Model
{
    protected $casts = [
        'highlights' => 'array',
        'metrics' => 'array',
        'links' => 'array',
    ];
}
