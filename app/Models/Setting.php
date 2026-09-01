<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'id',
        'tenant_id',
        'businessName',
        'businessAddress',
        'businessPhone',
        'businessEmail',
        'currency',
        'categories',
        'reportFooter',
        'lowStockThreshold',
        'transactionEditLimitDays',
        'fontFamily',
    ];

    protected $casts = [
        'categories' => 'array',
        'lowStockThreshold' => 'integer',
        'transactionEditLimitDays' => 'integer',
    ];
}
