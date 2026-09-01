<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

echo "====================================================================\n";
echo "   HYSAM / VMARKET POS - SYSTEM-WIDE FORM SUBMISSION SCANNER\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);
session(['tenant_id' => 'default-tenant']);

// Log in as super admin
$admin = \App\Models\User::where('role', 'admin')->first();
if ($admin) {
    auth()->login($admin);
    session(['user_id' => $admin->id]);
}

$postRoutes = [];
foreach (Route::getRoutes()->getRoutes() as $route) {
    if (in_array('POST', $route->methods()) && str_contains($route->uri(), 'saas') || str_contains($route->uri(), 'users') || str_contains($route->uri(), 'pos') || str_contains($route->uri(), 'products') || str_contains($route->uri(), 'stock')) {
        $postRoutes[] = [
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    }
}

echo "Found " . count($postRoutes) . " critical POST form submission endpoints:\n";
foreach ($postRoutes as $r) {
    echo " • POST /{$r['uri']} (Route: {$r['name']})\n";
}

echo "\nScanning form validation and submission handling...\n\n";
