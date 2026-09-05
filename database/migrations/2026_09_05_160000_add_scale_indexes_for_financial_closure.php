<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * High-volume composite indexes for enterprise merchant scale and O(1) financial reporting.
     */
    public function up(): void
    {
        // 1. Payments: High-speed invoice aggregation and payment lookup
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['saleId', 'method'], 'idx_payments_sale_method');
                $table->index(['tenant_id', 'created_at'], 'idx_payments_tenant_created');
            });
        }

        // 2. Sales Returns: Instant return credit aggregation per sale
        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->index('saleId', 'idx_sales_returns_sale_id');
                $table->index(['tenant_id', 'created_at'], 'idx_returns_tenant_created');
            });
        }

        // 3. Sales: Branch-scoped debtor resolution and customer sales
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->index(['customerId', 'warehouse_id'], 'idx_sales_customer_warehouse');
                $table->index(['tenant_id', 'customerId'], 'idx_sales_tenant_customer');
            });
        }

        // 4. Customer Ledgers: Fast ledger retrieval and recent payments lookup
        if (Schema::hasTable('customer_ledgers')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->index(['customer_id', 'type'], 'idx_ledgers_customer_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('idx_payments_sale_method');
                $table->dropIndex('idx_payments_tenant_created');
            });
        }

        if (Schema::hasTable('sales_returns')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->dropIndex('idx_sales_returns_sale_id');
                $table->dropIndex('idx_returns_tenant_created');
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex('idx_sales_customer_warehouse');
                $table->dropIndex('idx_sales_tenant_customer');
            });
        }

        if (Schema::hasTable('customer_ledgers')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->dropIndex('idx_ledgers_customer_type');
            });
        }
    }
};
