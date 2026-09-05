<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('customer_ledgers') || !Schema::hasColumn('customer_ledgers', 'warehouse_id')) {
            return;
        }

        // 1. Backfill customer_ledgers with direct sale_id linkage
        DB::statement("
            UPDATE customer_ledgers
            SET warehouse_id = (
                SELECT sales.warehouse_id
                FROM sales
                WHERE sales.id = customer_ledgers.sale_id
                LIMIT 1
            )
            WHERE customer_ledgers.warehouse_id IS NULL
              AND customer_ledgers.sale_id IS NOT NULL
        ");

        // 2. For remaining ledgers where sale_id IS NULL, infer from customer's latest sale or first tenant warehouse
        $unassignedLedgers = DB::table('customer_ledgers')
            ->whereNull('warehouse_id')
            ->get(['id', 'customer_id', 'tenant_id']);

        foreach ($unassignedLedgers as $ledger) {
            $inferredWarehouseId = DB::table('sales')
                ->where('customerId', $ledger->customer_id)
                ->whereNotNull('warehouse_id')
                ->orderBy('created_at', 'desc')
                ->value('warehouse_id');

            if (!$inferredWarehouseId) {
                $inferredWarehouseId = DB::table('warehouses')
                    ->where('tenant_id', $ledger->tenant_id ?? 'default-tenant')
                    ->orderBy('id', 'asc')
                    ->value('id');
            }

            if ($inferredWarehouseId) {
                DB::table('customer_ledgers')
                    ->where('id', $ledger->id)
                    ->update(['warehouse_id' => $inferredWarehouseId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration: no column rollback needed
    }
};
