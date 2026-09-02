<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_logs') && !Schema::hasColumn('inventory_logs', 'tenant_id')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });

            DB::table('inventory_logs')->whereNull('tenant_id')->update(['tenant_id' => 'default-tenant']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_logs') && Schema::hasColumn('inventory_logs', 'tenant_id')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
