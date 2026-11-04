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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'whatsapp_phone')) {
                    $table->string('whatsapp_phone', 30)->nullable()->after('email')->index();
                }
                if (!Schema::hasColumn('users', 'whatsapp_verified_at')) {
                    $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_phone');
                }
                if (!Schema::hasColumn('users', 'whatsapp_verify_token')) {
                    $table->string('whatsapp_verify_token', 32)->nullable()->after('whatsapp_verified_at')->index();
                }
                if (!Schema::hasColumn('users', 'whatsapp_verify_expires_at')) {
                    $table->timestamp('whatsapp_verify_expires_at')->nullable()->after('whatsapp_verify_token');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('users', 'whatsapp_verify_expires_at')) $columns[] = 'whatsapp_verify_expires_at';
                if (Schema::hasColumn('users', 'whatsapp_verify_token')) $columns[] = 'whatsapp_verify_token';
                if (Schema::hasColumn('users', 'whatsapp_verified_at')) $columns[] = 'whatsapp_verified_at';
                if (Schema::hasColumn('users', 'whatsapp_phone')) $columns[] = 'whatsapp_phone';
                
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};

