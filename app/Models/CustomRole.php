<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomRole extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'label',
        'description',
        'badgeBg',
        'badgeText',
        'badgeBorder',
        'isSystem',
        'modulePermissions',
        'allowedModules',
    ];

    protected $casts = [
        'isSystem' => 'boolean',
        'modulePermissions' => 'array',
        'allowedModules' => 'array',
    ];
}
