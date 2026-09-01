<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Http\Controllers\SaaS\SaaSController;
use Illuminate\Http\Request;

echo "====================================================================\n";
echo "   TESTING SAAS PUBLIC SELF-REGISTRATION FORM SUBMISSION\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);
session()->forget('tenant_id');
session()->forget('user_id');

Tenant::where('owner_email', 'test_owner_reg@vmarketpos.com')->delete();
User::withoutGlobalScopes()->where('email', 'test_owner_reg@vmarketpos.com')->delete();

$controller = new SaaSController();

$req = Request::create('/saas/register', 'POST', [
    'business_name' => 'Registration Test Store',
    'owner_name'    => 'Test Owner Reg',
    'owner_email'   => 'test_owner_reg@vmarketpos.com',
    'owner_phone'   => '08011223344',
    'password'      => 'password123',
    'plan'          => 'pro',
]);

try {
    $res = $controller->processRegister($req);
    echo "• Redirect Target: " . $res->getTargetUrl() . "\n";
    echo "• Session Tenant ID: " . session('tenant_id') . "\n";
    
    $tenant = Tenant::where('owner_email', 'test_owner_reg@vmarketpos.com')->first();
    echo "• Created Tenant ID: " . ($tenant->id ?? 'NONE') . "\n";
    
    $user = User::withoutGlobalScopes()->where('email', 'test_owner_reg@vmarketpos.com')->first();
    echo "• Created User Tenant ID: " . ($user->tenant_id ?? 'NONE') . "\n";
    
    $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    echo "• Created Warehouse Tenant ID: " . ($wh->tenant_id ?? 'NONE') . "\n";

    if ($res->isRedirect() && $tenant && $user && $wh) {
        echo "\n✅ SUCCESS: Self-registration form submission completed 100% cleanly!\n";
    } else {
        echo "\n❌ FAILURE: Self-registration submission failed!\n";
    }
} catch (\Exception $e) {
    echo "\n❌ EXCEPTION DURING SUBMISSION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
