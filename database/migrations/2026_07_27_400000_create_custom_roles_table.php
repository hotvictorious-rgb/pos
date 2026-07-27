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
        Schema::create('custom_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('badgeBg')->nullable();
            $table->string('badgeText')->nullable();
            $table->string('badgeBorder')->nullable();
            $table->boolean('isSystem')->default(false);
            $table->json('modulePermissions')->nullable();
            $table->json('allowedModules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_roles');
    }
};
