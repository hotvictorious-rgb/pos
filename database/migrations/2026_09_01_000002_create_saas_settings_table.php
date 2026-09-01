<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default SaaS platform settings
        $defaults = [
            'platform_name'        => 'Hysam Multi-Branch POS SaaS',
            'support_email'        => 'support@hysamventures.com',
            'support_phone'        => '+234 800 000 0000',
            'currency_symbol'      => '₦',
            'trial_days'           => '14',
            'allow_registration'   => '1',
            'monthly_price_basic'  => '15000',
            'monthly_price_pro'    => '35000',
            'monthly_price_enterprise' => '75000',
            'bank_name'             => 'Zenith Bank Plc',
            'bank_account_number'   => '1012345678',
            'bank_account_name'     => 'Hysam Ventures SaaS Ltd',
            'bank_instructions'     => 'Please pay into the account above and send payment receipt to support@hysamventures.com',
            'paystack_enabled'      => '1',
            'paystack_public_key'   => 'pk_test_sample_key_12345',
            'paystack_secret_key'   => 'sk_test_sample_key_12345',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('saas_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_settings');
    }
};
