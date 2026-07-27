<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'id',
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
