<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id')->nullable()->index();
                $table->string('sale_id')->index();
                $table->unsignedBigInteger('sale_item_id')->nullable()->index();
                $table->string('product_id')->index();
                $table->unsignedBigInteger('warehouse_id')->index();
                $table->integer('reserved_qty')->default(0);
                $table->integer('fulfilled_qty')->default(0);
                $table->integer('cancelled_qty')->default(0);
                $table->enum('status', ['ACTIVE', 'PARTIALLY_FULFILLED', 'FULFILLED', 'CANCELLED'])->default('ACTIVE')->index();
                $table->string('customer_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
