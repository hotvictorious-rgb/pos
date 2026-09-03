<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'sale_items',
            'sales_returns',
            'payments',
            'transfer_items',
            'cashier_shifts',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('tenant_id', 100)->nullable()->index();
                });

                // Backfill existing records with 'default-tenant'
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => 'default-tenant']);
            }
        }

        // Add transferAmount to sales table if not present for explicit mixed tender tracking
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'transferAmount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->double('transferAmount')->default(0)->after('posAmount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'sale_items',
            'sales_returns',
            'payments',
            'transfer_items',
            'cashier_shifts',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'transferAmount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('transferAmount');
            });
        }
    }
};
