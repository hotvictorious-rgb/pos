<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Sale extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'warehouse_id',
        'customerName',
        'totalAmount',
        'paidAmount',
        'tenderedAmount',
        'changeAmount',
        'cashAmount',
        'posAmount',
        'transferAmount',
        'note',
        'status',
        'sale_type',
        'customerId',
        'deliveryStatus',
        'deliveredAt',
        'deliveredBy',
        'returnReason',
        'userId',
        'userName',
        'createdAt',
    ];

    protected $casts = [
        'totalAmount' => 'double',
        'paidAmount' => 'double',
        'tenderedAmount' => 'double',
        'changeAmount' => 'double',
        'cashAmount' => 'double',
        'posAmount' => 'double',
        'transferAmount' => 'double',
        'warehouse_id' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'saleId', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customerId', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'saleId', 'id');
    }

    public function returns()
    {
        return $this->hasMany(SalesReturn::class, 'saleId', 'id');
    }

    /**
     * Authoritatively calculated current outstanding balance for this sale invoice.
     * Consumes canonical AccountingReportService logic.
     */
    public function getInvoiceBalanceAttribute(): float
    {
        return app(\App\Services\Accounting\AccountingReportService::class)->calculateInvoiceBalance($this);
    }
}
