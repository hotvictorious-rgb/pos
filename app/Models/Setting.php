<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Setting extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'int';

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
        'owner_whatsapp_phone',
        'whatsapp_report_enabled',
        'whatsapp_report_time',
        'whatsapp_branch_breakdown',
        'whatsapp_include_reversals',
        'whatsapp_include_low_stock',
    ];

    protected $casts = [
        'categories' => 'array',
        'lowStockThreshold' => 'integer',
        'transactionEditLimitDays' => 'integer',
        'whatsapp_report_enabled' => 'boolean',
        'whatsapp_branch_breakdown' => 'boolean',
        'whatsapp_include_reversals' => 'boolean',
        'whatsapp_include_low_stock' => 'boolean',
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
