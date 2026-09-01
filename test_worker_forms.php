<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Warehouse;
use App\Http\Controllers\Web\UserController;
use Illuminate\Http\Request;

echo "====================================================================\n";
echo "   TESTING WORKERS & ROLES FORM SUBMISSIONS\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);
session(['tenant_id' => 'default-tenant']);

$admin = User::where('role', 'admin')->first();
auth()->login($admin);
session(['user_id' => $admin->id]);

User::withoutGlobalScopes()->where('email', 'worker_form_test@vmarketpos.com')->delete();

$controller = new UserController();

// 1. Add Worker Form
echo "[1/4] Testing Add New Worker Form (POST /users)...\n";
$reqAdd = Request::create('/users', 'POST', [
    'name' => 'Worker Form Test',
    'email' => 'worker_form_test@vmarketpos.com',
    'password' => 'password123',
    'role' => 'manager',
]);

try {
    $resAdd = $controller->store($reqAdd);
    $newUser = User::withoutGlobalScopes()->where('email', 'worker_form_test@vmarketpos.com')->first();
    echo "   • Worker Created: " . ($newUser ? "{$newUser->name} (Role: {$newUser->role})" : "FAILED") . "\n";
    if (!$newUser) throw new \Exception("Add worker failed");
    echo "   ✅ PASS\n\n";
} catch (\Exception $e) {
    echo "   ❌ ERROR IN ADD WORKER: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Edit Worker Form
echo "[2/4] Testing Edit Worker Form (POST /users/update/{id})...\n";
$reqEdit = Request::create("/users/update/{$newUser->id}", 'POST', [
    'name' => 'Worker Form Test Updated',
    'email' => 'worker_form_test@vmarketpos.com',
    'role' => 'viewer',
]);

try {
    $resEdit = $controller->update($reqEdit, $newUser->id);
    $newUser->refresh();
    echo "   • Worker Updated: {$newUser->name} (New Role: {$newUser->role})\n";
    if ($newUser->role !== 'viewer') throw new \Exception("Update worker role failed");
    echo "   ✅ PASS\n\n";
} catch (\Exception $e) {
    echo "   ❌ ERROR IN EDIT WORKER: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Lock/Unlock Worker Toggle
echo "[3/4] Testing Lock/Unlock Worker (POST /users/toggle/{id})...\n";
try {
    $controller->toggleStatus($newUser->id);
    $newUser->refresh();
    echo "   • Worker Lock Status: " . ($newUser->disabled ? 'LOCKED' : 'ACTIVE') . "\n";
    if (!$newUser->disabled) throw new \Exception("Toggle worker lock failed");
    echo "   ✅ PASS\n\n";
} catch (\Exception $e) {
    echo "   ❌ ERROR IN LOCK WORKER: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Reset Password Form
echo "[4/4] Testing Reset Password Form (POST /users/reset-password/{id})...\n";
$reqReset = Request::create("/users/reset-password/{$newUser->id}", 'POST', [
    'new_password' => 'newpassword123',
]);

try {
    $controller->resetPassword($reqReset, $newUser->id);
    echo "   • Password Reset Executed Cleanly\n";
    echo "   ✅ PASS\n\n";
} catch (\Exception $e) {
    echo "   ❌ ERROR IN RESET PASSWORD: " . $e->getMessage() . "\n";
    exit(1);
}

echo "====================================================================\n";
echo "🌟 ALL 4 WORKER FORM SUBMISSIONS PASSED 100% CLEANLY!\n";
echo "====================================================================\n";
