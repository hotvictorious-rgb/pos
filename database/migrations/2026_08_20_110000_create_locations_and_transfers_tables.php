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
        // 1. Warehouses / Branch Locations
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Stock Levels Per Warehouse (Physical on hand vs Allocated unsupplied)
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->index();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->integer('physical_stock')->default(0); // Actual physical units on ground
            $table->integer('allocated_stock')->default(0); // Sold items not yet picked up
            $table->integer('min_stock_alert')->default(5);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });

        // 3. Customers & Debt Ledgers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->double('total_debt')->default(0);
            $table->double('credit_limit')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('sale_id')->nullable()->index();
            $table->string('type'); // INVOICE, PAYMENT, RETURN, ADJUSTMENT
            $table->double('amount');
            $table->double('balance_after');
            $table->string('payment_method')->nullable(); // CASH, TRANSFER, POS
            $table->string('reference_no')->nullable();
            $table->string('recorded_by');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        // 4. Multi-Location Inter-Branch Transfers (Anti-Theft 2-Step Handshake)
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->unsignedBigInteger('source_warehouse_id')->index();
            $table->unsignedBigInteger('destination_warehouse_id')->index();
            $table->string('status')->default('DISPATCHED'); // DISPATCHED, RECEIVED, DISCREPANCY, CANCELLED
            $table->string('carrier_name')->nullable();
            $table->string('dispatched_by');
            $table->string('received_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('discrepancy_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('source_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('destination_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });

        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id')->index();
            $table->string('product_id');
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->integer('dispatched_qty');
            $table->integer('received_qty')->default(0);
            $table->integer('discrepancy_qty')->default(0); // dispatched - received
            $table->timestamps();

            $table->foreign('transfer_id')->references('id')->on('transfers')->onDelete('cascade');
        });

        // 5. Cashier End-of-Day Balancing / Shift Reconciliations
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->string('cashier_id');
            $table->string('cashier_name');
            $table->double('opening_float')->default(0);
            $table->double('cash_sales')->default(0);
            $table->double('transfer_sales')->default(0);
            $table->double('pos_sales')->default(0);
            $table->double('debt_recovered')->default(0);
            $table->double('expected_cash')->default(0);
            $table->double('counted_cash')->default(0);
            $table->double('difference')->default(0); // counted - expected
            $table->string('status')->default('OPEN'); // OPEN, CLOSED, AUDITED
            $table->text('auditor_notes')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('customer_ledgers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('warehouses');
    }
};
