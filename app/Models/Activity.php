<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Activity extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
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
