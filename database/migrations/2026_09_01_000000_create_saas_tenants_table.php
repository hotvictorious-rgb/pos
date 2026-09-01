<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. tenant-uuid or tenant-slug
            $table->string('name'); // Business/Company name
            $table->string('owner_email')->unique();
            $table->string('owner_phone')->nullable();
            $table->string('plan')->default('basic'); // basic, pro, enterprise
            $table->string('status')->default('active'); // active, trial, suspended
            $table->timestamp('trial_ends_at')->nullable();
            $table->integer('max_branches')->default(1);
            $table->integer('max_users')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
