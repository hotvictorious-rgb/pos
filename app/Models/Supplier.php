<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'contact_info',
        'lead_time',
    ];

    protected $casts = [
        'lead_time' => 'integer',
    ];
}
