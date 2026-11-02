<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            try {
                // If MySQL / MariaDB, safely change ENUM to VARCHAR(32) so any valid status string like 'RETURNED' is accepted
                DB::statement("ALTER TABLE stock_reservations MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE'");
            } catch (\Throwable $e) {
                // Fallback for SQLite / other DB engines during tests
                Schema::table('stock_reservations', function (Blueprint $table) {
                    $table->string('status', 32)->default('ACTIVE')->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_reservations')) {
            try {
                DB::statement("ALTER TABLE stock_reservations MODIFY COLUMN status ENUM('ACTIVE', 'PARTIALLY_FULFILLED', 'FULFILLED', 'CANCELLED', 'RETURNED') NOT NULL DEFAULT 'ACTIVE'");
            } catch (\Throwable $e) {
                // SQLite fallback
            }
        }
    }
};
