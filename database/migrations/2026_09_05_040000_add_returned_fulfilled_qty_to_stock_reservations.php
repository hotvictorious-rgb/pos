<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_reservations', 'returned_fulfilled_qty')) {
                    $table->integer('returned_fulfilled_qty')->default(0)->after('fulfilled_qty');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                if (Schema::hasColumn('stock_reservations', 'returned_fulfilled_qty')) {
                    $table->dropColumn('returned_fulfilled_qty');
                }
            });
        }
    }
};
