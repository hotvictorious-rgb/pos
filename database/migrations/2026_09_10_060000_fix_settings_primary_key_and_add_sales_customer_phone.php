<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add customerPhone column to sales table if not already present
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'customerPhone')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('customerPhone')->nullable()->after('customerName')->index();
            });
        }

        // 2. Fix settings table id auto-increment for multi-tenant support (MySQL)
        if (Schema::hasTable('settings')) {
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE `settings` MODIFY COLUMN `id` INT NOT NULL AUTO_INCREMENT');
                }
            } catch (\Throwable $e) {
                // Non-fatal: Setting model booted creating hook provides software-level auto-increment fallback
            }
        }

        // 3. Ensure password_reset_tokens table exists
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'customerPhone')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('customerPhone');
            });
        }
    }
};
