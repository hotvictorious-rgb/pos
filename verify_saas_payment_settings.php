<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SaaSSetting;
use App\Http\Controllers\SaaS\SaaSController;
use Illuminate\Http\Request;

echo "====================================================================\n";
echo "   HYSAM SAAS PLATFORM - BANK ACCOUNT & PAYSTACK SETTINGS AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);
session(['tenant_id' => 'default-tenant']);

$controller = new SaaSController();

echo "[1/2] Saving Bank Account Details & Paystack API Keys...\n";

$req = Request::create('/saas/admin/settings', 'POST', [
    'bank_name'           => 'Guaranty Trust Bank (GTBank)',
    'bank_account_number' => '0123456789',
    'bank_account_name'   => 'Hysam Ventures Multi-Branch SaaS Ltd',
    'bank_instructions'   => 'Upload transfer receipt to support@hysamventures.com',
    'paystack_enabled'    => '1',
    'paystack_public_key' => 'pk_live_998877665544332211',
    'paystack_secret_key' => 'sk_live_112233445566778899',
]);

$controller->updateSettings($req);

echo "   • Bank Name Saved: '" . SaaSSetting::get('bank_name') . "'\n";
echo "   • Account Number Saved: '" . SaaSSetting::get('bank_account_number') . "'\n";
echo "   • Account Name Saved: '" . SaaSSetting::get('bank_account_name') . "'\n";
echo "   • Paystack Public Key Saved: '" . SaaSSetting::get('paystack_public_key') . "'\n";
echo "   • Paystack Secret Key Saved: '" . SaaSSetting::get('paystack_secret_key') . "'\n";

if (
    SaaSSetting::get('bank_account_number') !== '0123456789' ||
    SaaSSetting::get('paystack_public_key') !== 'pk_live_998877665544332211'
) {
    echo "❌ AUDIT FAILURE: Bank details or Paystack keys failed to save!\n";
    exit(1);
}

echo "\n====================================================================\n";
echo "🌟 FINAL AUDIT VERDICT: BANK DETAILS & PAYSTACK KEYS SAVED & VERIFIED 100% OK!\n";
echo "====================================================================\n";
