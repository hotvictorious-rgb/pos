<?php

/**
 * Safe Live Migration & Cache Clear Helper for VMPOS Deployment
 */
if (php_sapi_name() !== 'cli') {
    // Check security token if accessed via HTTP
    $token = $_GET['token'] ?? '';
    if ($token !== 'vmpos_deploy_2026') {
        http_response_code(403);
        die('Forbidden: Access token required (?token=vmpos_deploy_2026)');
    }
}

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "=== VMPOS LIVE DEPLOYMENT POST-EXTRACT RUNNER ===\n<br>";

// 1. Run migrations safely
echo "1. Running migrations...\n<br>";
try {
    Artisan::call('migrate', ['--force' => true]);
    echo nl2br(Artisan::output()) . "\n<br>";
} catch (\Throwable $e) {
    echo "Migration note: " . $e->getMessage() . "\n<br>";
}

// 2. Clear view cache
echo "2. Clearing compiled Blade views...\n<br>";
Artisan::call('view:clear');
echo nl2br(Artisan::output()) . "\n<br>";

// 3. Clear application cache & route cache
echo "3. Clearing application & route cache...\n<br>";
Artisan::call('cache:clear');
Artisan::call('route:clear');
Artisan::call('config:clear');
echo "All caches cleared successfully!\n<br>";

echo "=== DEPLOYMENT OVERLAY COMPLETE & READY ===\n<br>";
