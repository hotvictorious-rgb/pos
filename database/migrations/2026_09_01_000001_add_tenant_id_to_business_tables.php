<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $tables = [
        'users',
        'warehouses',
        'products',
        'stock_levels',
        'customers',
        'sales',
        'suppliers',
        'transfers',
        'stock_adjustments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('tenant_id')->nullable()->index();
                });

                // Assign existing historical records to 'default-tenant'
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => 'default-tenant']);
            }
        }

        // Create default tenant record if not present
        if (Schema::hasTable('tenants')) {
            DB::table('tenants')->updateOrInsert(
                ['id' => 'default-tenant'],
                [
                    'name' => 'Default Business Tenant',
                    'owner_email' => 'admin@hysamventures.com',
                    'owner_phone' => '08000000000',
                    'plan' => 'enterprise',
                    'status' => 'active',
                    'max_branches' => 999,
                    'max_users' => 999,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
