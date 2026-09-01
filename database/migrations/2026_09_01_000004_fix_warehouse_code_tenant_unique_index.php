<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            try {
                $table->dropUnique('warehouses_code_unique');
            } catch (\Throwable $e) {
                // Ignore if unique constraint name differs
            }
            $table->unique(['tenant_id', 'code'], 'warehouses_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            try {
                $table->dropUnique('warehouses_tenant_code_unique');
            } catch (\Throwable $e) {}
            $table->unique('code', 'warehouses_code_unique');
        });
    }
};
