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
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 100)->index();
            $table->string('operation', 50)->index();
            $table->string('idempotency_key', 255)->index();
            $table->string('user_id', 100)->index();
            $table->string('payload_fingerprint', 64);
            $table->string('status', 20)->default('COMPLETED'); // PENDING, COMPLETED, FAILED
            $table->longText('response_data')->nullable();
            $table->timestamps();

            // Unique composite index guaranteeing strict tenant & operation idempotency at database level
            $table->unique(['tenant_id', 'operation', 'idempotency_key'], 'idempotency_tenant_op_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
