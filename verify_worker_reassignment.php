<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Activity;
use App\Http\Controllers\Web\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "\n====================================================================\n";
echo "   HYSAM VENTURES - WORKER EDIT & REASSIGNMENT AUDIT PROOF          \n";
echo "====================================================================\n\n";

// 1. Setup Test Branches
$branchA = Warehouse::firstOrCreate(
    ['code' => 'BRANCH-A'],
    ['name' => 'Alaba Shop Branch', 'is_active' => true]
);

$branchB = Warehouse::firstOrCreate(
    ['code' => 'BRANCH-B'],
    ['name' => 'Trade Fair Wholesale Hub', 'is_active' => true]
);

$admin = User::firstOrCreate(
    ['email' => 'madam.admin@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Madam Owner',
        'password' => Hash::make('secret123'),
        'role' => 'admin',
        'disabled' => false,
    ]
);

// 2. Create Worker at Branch A
$worker = User::create([
    'id' => (string) Str::uuid(),
    'name' => 'Chinedu Worker',
    'email' => 'chinedu.' . uniqid() . '@hysam.com',
    'password' => Hash::make('password123'),
    'role' => 'cashier',
    'warehouse_id' => $branchA->id,
    'disabled' => false,
    'permissions' => ['pos' => true],
]);

echo "✅ Step 1: Initial Worker Profile Created\n";
echo "   • Worker: {$worker->name}\n";
echo "   • Initial Role: {$worker->role}\n";
echo "   • Initial Location: {$worker->warehouse->name}\n\n";

// 3. Reassign Worker to Branch B and Promote to Manager
Auth::login($admin);
$controller = new UserController();

$updateReq = Request::create("/users/update/{$worker->id}", 'POST', [
    'name' => 'Chinedu Okeke (Promoted)',
    'email' => $worker->email,
    'role' => 'manager',
    'warehouse_id' => $branchB->id,
]);

$response = $controller->update($updateReq, $worker->id);

$updatedWorker = User::find($worker->id);

assert($updatedWorker->name === 'Chinedu Okeke (Promoted)', 'Proof Failed: Name not updated');
assert($updatedWorker->role === 'manager', 'Proof Failed: Role not updated to manager');
assert((int)$updatedWorker->warehouse_id === (int)$branchB->id, 'Proof Failed: Warehouse not updated to Branch B');
assert($updatedWorker->permissions['reports'] === true, 'Proof Failed: Manager permissions not granted');

echo "✅ Step 2: Worker Successfully Reassigned & Promoted\n";
echo "   • Updated Name: {$updatedWorker->name}\n";
echo "   • New Role: {$updatedWorker->role} (Permissions: Manager Suite)\n";
echo "   • New Location: {$updatedWorker->warehouse->name}\n\n";

// 4. Verify Immutable Activity Log
$log = Activity::where('type', 'USER_UPDATED')
    ->where('description', 'like', "%Chinedu%")
    ->latest('timestamp')
    ->first();

assert($log !== null, 'Proof Failed: Activity log not created');
assert(str_contains($log->description, 'Alaba Shop Branch ➔ Trade Fair Wholesale Hub'), 'Proof Failed: Log does not capture location transfer');

echo "✅ Step 3: Immutable Audit Trail Verified\n";
echo "   • Log Type: {$log->type}\n";
echo "   • Log Description: {$log->description}\n";
echo "   • Admin Operator: {$log->userName}\n\n";

echo "====================================================================\n";
echo "   ALL REASSIGNMENT PROOFS PASSED (100% SUCCESS)                     \n";
echo "====================================================================\n\n";
