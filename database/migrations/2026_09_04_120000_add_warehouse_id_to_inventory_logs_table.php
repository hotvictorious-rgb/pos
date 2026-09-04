<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_logs') && !Schema::hasColumn('inventory_logs', 'warehouse_id')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index()->after('productId');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_logs') && Schema::hasColumn('inventory_logs', 'warehouse_id')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
