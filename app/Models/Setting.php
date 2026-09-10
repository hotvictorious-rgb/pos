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

    protected static function booted()
    {
        static::creating(function ($setting) {
            if (empty($setting->id)) {
                $maxId = (int) static::withoutGlobalScopes()->max('id');
                $setting->id = max(1, $maxId + 1);
            }
        });
    }
}
