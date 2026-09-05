<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_ledgers') && !Schema::hasColumn('customer_ledgers', 'warehouse_id')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('sale_id')->index();
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customer_ledgers') && Schema::hasColumn('customer_ledgers', 'warehouse_id')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
