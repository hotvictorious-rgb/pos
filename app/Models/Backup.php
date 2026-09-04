<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'filename',
        'size',
        'created_by',
    ];

    public function isPlatformBackup(): bool
    {
        return empty($this->tenant_id);
    }

    public function isTenantBackup(): bool
    {
        return !empty($this->tenant_id);
    }
}
