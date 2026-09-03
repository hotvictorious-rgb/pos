<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class IdempotencyRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'idempotency_records';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'operation',
        'idempotency_key',
        'user_id',
        'payload_fingerprint',
        'status',
        'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
    ];
}
