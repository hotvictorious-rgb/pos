<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\BelongsToTenant;

class Supplier extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_info',
        'lead_time',
    ];

    protected $casts = [
        'lead_time' => 'integer',
    ];
}
