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
        // 1. Stock Adjustments (Damages, Expired, Lost Goods)
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('product_id')->index();
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->string('type'); // DAMAGE, EXPIRED, LOST, FOUND
            $table->integer('quantity');
            $table->text('reason')->nullable();
            $table->string('recorded_by');
            $table->string('status')->default('APPROVED'); // PENDING, APPROVED, REJECTED
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });

        // 2. Add warehouse_id to users table for branch-specific staff assignment
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('role')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('warehouse_id');
            });
        }
    }
};
