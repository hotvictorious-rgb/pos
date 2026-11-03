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
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (!Schema::hasColumn('settings', 'owner_whatsapp_phone')) {
                    $table->string('owner_whatsapp_phone')->nullable()->after('businessPhone');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_report_enabled')) {
                    $table->boolean('whatsapp_report_enabled')->default(false)->after('owner_whatsapp_phone');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_report_time')) {
                    $table->string('whatsapp_report_time', 10)->default('21:00')->after('whatsapp_report_enabled');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_branch_breakdown')) {
                    $table->boolean('whatsapp_branch_breakdown')->default(true)->after('whatsapp_report_time');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_include_reversals')) {
                    $table->boolean('whatsapp_include_reversals')->default(true)->after('whatsapp_branch_breakdown');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_include_low_stock')) {
                    $table->boolean('whatsapp_include_low_stock')->default(true)->after('whatsapp_include_reversals');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_include_inventory')) {
                    $table->boolean('whatsapp_include_inventory')->default(true)->after('whatsapp_include_low_stock');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_include_transfers')) {
                    $table->boolean('whatsapp_include_transfers')->default(true)->after('whatsapp_include_inventory');
                }
                if (!Schema::hasColumn('settings', 'whatsapp_include_deliveries')) {
                    $table->boolean('whatsapp_include_deliveries')->default(true)->after('whatsapp_include_transfers');
                }
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouses', 'manager_whatsapp_phone')) {
                    $table->string('manager_whatsapp_phone')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('warehouses', 'whatsapp_report_enabled')) {
                    $table->boolean('whatsapp_report_enabled')->default(true)->after('manager_whatsapp_phone');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $cols = array_filter([
                    'owner_whatsapp_phone',
                    'whatsapp_report_enabled',
                    'whatsapp_report_time',
                    'whatsapp_branch_breakdown',
                    'whatsapp_include_reversals',
                    'whatsapp_include_low_stock',
                    'whatsapp_include_inventory',
                    'whatsapp_include_transfers',
                    'whatsapp_include_deliveries',
                ], fn ($col) => Schema::hasColumn('settings', $col));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $cols = array_filter([
                    'manager_whatsapp_phone',
                    'whatsapp_report_enabled',
                ], fn ($col) => Schema::hasColumn('warehouses', $col));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

