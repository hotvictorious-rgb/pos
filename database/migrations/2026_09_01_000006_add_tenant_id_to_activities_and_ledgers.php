<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activities') && !Schema::hasColumn('activities', 'tenant_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
            DB::table('activities')->whereNull('tenant_id')->update(['tenant_id' => 'default-tenant']);
        }

        if (Schema::hasTable('customer_ledgers') && !Schema::hasColumn('customer_ledgers', 'tenant_id')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
            DB::table('customer_ledgers')->whereNull('tenant_id')->update(['tenant_id' => 'default-tenant']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'tenant_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('customer_ledgers') && Schema::hasColumn('customer_ledgers', 'tenant_id')) {
            Schema::table('customer_ledgers', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
