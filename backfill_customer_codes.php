<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;

$customers = Customer::whereNull('customer_code')->orWhere('customer_code', '')->get();
foreach ($customers as $c) {
    $c->customer_code = 'CUST-' . str_pad($c->id, 4, '0', STR_PAD_LEFT);
    $c->saveQuietly();
}

echo "Successfully backfilled " . Customer::count() . " customers with customer_code!\n";
