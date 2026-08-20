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
        // 1. Performance Indexes on Sales for 10+ year query scalability
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->index('createdAt');
                $table->index('deliveryStatus');
                $table->index('userId');
                $table->index('customerName');
            });
        }

        // 2. Performance Indexes on Sale Items
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->index('productId');
            });
        }

        // 3. Performance Indexes on Inventory Logs & Activities
        if (Schema::hasTable('inventory_logs')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->index('timestamp');
                $table->index('type');
            });
        }

        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->index('timestamp');
                $table->index('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropIndex(['createdAt']);
                $table->dropIndex(['deliveryStatus']);
                $table->dropIndex(['userId']);
                $table->dropIndex(['customerName']);
            });
        }

        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropIndex(['productId']);
            });
        }

        if (Schema::hasTable('inventory_logs')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->dropIndex(['timestamp']);
                $table->dropIndex(['type']);
            });
        }

        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropIndex(['timestamp']);
                $table->dropIndex(['type']);
            });
        }
    }
};
