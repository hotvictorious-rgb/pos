<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display all workers, roles, and branch assignments.
     */
    public function index()
    {
        $users = User::orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('users.index', compact('users', 'warehouses'));
    }

    /**
     * Create a new worker account with assigned role and permissions.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|string',
        ]);

        $userId = (string) Str::uuid();
        $creatorName = Auth::user()->name ?? 'Auditor / Admin';

        $role = $request->role;
        $permissions = match($role) {
            'viewer', 'executive_readonly' => ['view_only' => true, 'reports' => true, 'products' => true, 'stock' => true, 'transactions' => true, 'debts' => true, 'auditor' => true],
            default => ['pos' => true, 'products' => true, 'stockIn' => true, 'transfer' => true, 'reports' => true, 'debts' => true, 'returns' => true, 'adjustments' => true, 'users' => true],
        };

        $user = User::create([
            'id' => $userId,
            'name' => $request->name,
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role' => $role,
            'warehouse_id' => $request->warehouse_id ? (int) $request->warehouse_id : null,
            'disabled' => false,
            'permissions' => $permissions,
        ]);

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'USER_CREATED',
            'description' => "{$creatorName} created worker account for {$user->name} with role: {$role}",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $creatorName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('users.index')->with('success', "✓ Worker account for {$user->name} created successfully!");
    }

    /**
     * Anti-Theft Lock: Instantly enable/disable worker account.
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->disabled = !$user->disabled;
        $user->save();

        $action = $user->disabled ? 'LOCKED / DISABLED' : 'ACTIVATED';
        $creatorName = Auth::user()->name ?? 'Auditor / Admin';

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'USER_STATUS_CHANGED',
            'description' => "{$creatorName} {$action} worker account for {$user->name}",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $creatorName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('users.index')->with('success', "✓ Account for {$user->name} is now {$action}.");
    }

    /**
     * Update worker profile, assigned role, permissions, and branch location.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|string',
            'warehouse_id' => 'nullable',
        ]);

        $oldRole = $user->role;
        $oldWarehouse = $user->warehouse->name ?? 'All Branches';
        $newRole = $request->role;
        $newWarehouseId = $request->warehouse_id ? (int) $request->warehouse_id : null;

        $permissions = match($newRole) {
            'viewer', 'executive_readonly' => ['view_only' => true, 'reports' => true, 'products' => true, 'stock' => true, 'transactions' => true, 'debts' => true, 'auditor' => true],
            default => ['pos' => true, 'products' => true, 'stockIn' => true, 'transfer' => true, 'reports' => true, 'debts' => true, 'returns' => true, 'adjustments' => true, 'users' => true],
        };

        $user->name = $request->name;
        $user->email = strtolower(trim($request->email));
        $user->role = $newRole;
        $user->warehouse_id = $newWarehouseId;
        $user->permissions = $permissions;

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:6']);
            $user->password = Hash::make($request->password);
        }

        $user->save();
        $user->load('warehouse');

        $newWarehouseName = $user->warehouse->name ?? 'All Branches';
        $adminName = Auth::user()->name ?? 'Auditor / Admin';

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'USER_UPDATED',
            'description' => "{$adminName} updated worker {$user->name}: Role [{$oldRole} ➔ {$newRole}], Location [{$oldWarehouse} ➔ {$newWarehouseName}]",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $adminName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('users.index')->with('success', "✓ Worker account for {$user->name} updated successfully (Assigned to: {$newWarehouseName}).");
    }

    /**
     * Reset worker password.
     */
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'new_password' => 'required|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->new_password);
        $user->save();

        return redirect()->route('users.index')->with('success', "✓ Password for {$user->name} updated successfully.");
    }
}
