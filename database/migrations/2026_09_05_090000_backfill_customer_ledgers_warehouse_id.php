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

        // 1. Backfill customer_ledgers with direct deterministic sale_id linkage
        // Unlinked historical ledgers without a sale_id remain warehouse_id = NULL (legacy unattributed)
        // to prevent false or inaccurate branch attribution.
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration: no column rollback needed
    }
};
