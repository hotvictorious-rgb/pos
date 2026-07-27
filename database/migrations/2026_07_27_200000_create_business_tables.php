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
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code');
            $table->string('name');
            $table->string('size')->nullable();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('category');
            $table->double('unitPrice');
            $table->integer('currentStock')->default(0);
            $table->integer('minStockLevel')->default(2);
            $table->boolean('archived')->default(false);
            $table->string('userId')->nullable();
            $table->string('updatedAt');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('customerName')->nullable();
            $table->double('totalAmount');
            $table->double('paidAmount');
            $table->double('cashAmount');
            $table->double('posAmount');
            $table->text('note')->nullable();
            $table->string('status');
            $table->string('deliveryStatus');
            $table->string('deliveredAt')->nullable();
            $table->string('deliveredBy')->nullable();
            $table->text('returnReason')->nullable();
            $table->string('userId');
            $table->string('userName')->nullable();
            $table->string('createdAt');
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->string('saleId')->index();
            $table->string('productId');
            $table->string('productName');
            $table->integer('quantity');
            $table->double('unitPrice');
            $table->double('totalPrice');
            $table->string('code')->nullable();
            $table->string('productCode')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('saleId')->index();
            $table->double('amount');
            $table->string('method');
            $table->string('timestamp');
            $table->string('recordedBy');
            $table->string('createdAt')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('saleId')->index();
            $table->string('customerName')->nullable();
            $table->string('code');
            $table->string('productId');
            $table->string('productName');
            $table->integer('quantity');
            $table->double('refundAmount');
            $table->text('reason')->nullable();
            $table->string('createdAt');
            $table->string('userId');
            $table->string('userName')->nullable();
            $table->string('timestamp')->nullable();
            $table->string('productCode')->nullable();
            $table->boolean('wasDelivered')->default(false);
            $table->string('deliveryStatus')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('productId')->index();
            $table->string('type');
            $table->integer('quantity');
            $table->string('userId');
            $table->text('notes')->nullable();
            $table->string('timestamp');
            $table->string('productCode')->nullable();
            $table->string('productName')->nullable();
            $table->text('description')->nullable();
            $table->string('userName')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->text('description');
            $table->string('userId');
            $table->string('userName');
            $table->string('timestamp');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->integer('id')->primary()->default(1);
            $table->string('businessName');
            $table->text('businessAddress')->nullable();
            $table->string('businessPhone')->nullable();
            $table->string('businessEmail')->nullable();
            $table->string('currency');
            $table->json('categories');
            $table->text('reportFooter')->nullable();
            $table->integer('lowStockThreshold');
            $table->integer('transactionEditLimitDays');
            $table->string('fontFamily');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('settings');
    }
};
