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
                $table->string('product_id')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            Schema::table('stock_reservations', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->change();
            });
        }
    }
};
