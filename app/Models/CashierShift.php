<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class CashierShift extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'cashier_id',
        'cashier_name',
        'opening_float',
        'cash_sales',
        'transfer_sales',
        'pos_sales',
        'debt_recovered',
        'expected_cash',
        'counted_cash',
        'difference',
        'status', // OPEN, CLOSED, AUDITED
        'auditor_notes',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_float' => 'double',
        'cash_sales' => 'double',
        'transfer_sales' => 'double',
        'pos_sales' => 'double',
        'debt_recovered' => 'double',
        'expected_cash' => 'double',
        'counted_cash' => 'double',
        'difference' => 'double',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
