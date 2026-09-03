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
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->index()->after('tenant_id');
            $table->double('tenderedAmount')->default(0)->after('paidAmount');
            $table->double('changeAmount')->default(0)->after('tenderedAmount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['warehouse_id', 'tenderedAmount', 'changeAmount']);
        });
    }
};
