<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiVisitorMemory extends Model
{
    protected $primaryKey = 'visitor_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['visitor_id', 'memory'];
}
