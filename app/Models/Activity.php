<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'description',
        'userId',
        'userName',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
