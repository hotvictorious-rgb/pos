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
        $authUser = Auth::user();
        if ($authUser && !empty($authUser->warehouse_id)) {
            $users = User::where('warehouse_id', $authUser->warehouse_id)->orderBy('name')->get();
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->where('is_active', true)->get();
        } else {
            $users = User::orderBy('name')->get();
            $warehouses = Warehouse::where('is_active', true)->get();
        }

        return view('users.index', compact('users', 'warehouses'));
    }

    /**
     * Create a new worker account with assigned role and permissions.
     */
    public function store(Request $request)
    {
        $tenantId = session('tenant_id') ?? 'default-tenant';

        // Enforce SaaS Subscription Worker Account Limit
        if (config('saas.enabled')) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant && $tenant->max_users !== null) {
                $currentUsers = User::count();
                if ($currentUsers >= $tenant->max_users) {
                    $errorMsg = "🔒 Subscription Limit Reached: Your current plan allows a maximum of {$tenant->max_users} worker account(s). Please upgrade your subscription to add more staff.";
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    }
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }
            }
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|string',
        ]);

        $userId = (string) Str::uuid();
        $creatorName = Auth::user()->name ?? 'Auditor / Admin';

        $role = $request->role;
        if ($role === 'super_admin') {
            return back()->withErrors(['role' => 'Forbidden: The platform Super-Administrator role cannot be assigned. There is exactly one platform root Super Admin defined by system environment.']);
        }

        $warehouseId = null;
        if ($request->filled('warehouse_id')) {
            $wh = Warehouse::find($request->warehouse_id);
            if (!$wh) {
                return back()->withErrors(['warehouse_id' => 'Selected branch location does not exist in your business.']);
            }
            $warehouseId = $wh->id;
        }

        $permissions = match($role) {
            'cashier' => [
                'pos' => true,
                'debts' => true,
                'returns' => true,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'reports' => false,
                'users' => false,
            ],
            'sales_officer' => [
                'pos' => true,
                'debts' => true,
                'returns' => true,
                'reports' => true,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'users' => false,
            ],
            'storekeeper' => [
                'pos' => false,
                'debts' => false,
                'returns' => false,
                'products' => true,
                'stockIn' => true,
                'transfer' => true,
                'adjustments' => true,
                'reports' => false,
                'users' => false,
            ],
            'branch_manager' => [
                'pos' => true,
                'debts' => true,
                'returns' => true,
                'products' => true,
                'stockIn' => true,
                'transfer' => true,
                'adjustments' => true,
                'reports' => true,
                'users' => false,
            ],
            'viewer', 'executive_readonly' => [
                'pos' => false,
                'debts' => false,
                'returns' => false,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'view_only' => true,
                'reports' => true,
                'users' => false,
            ],
            'admin', 'super_admin' => [
                'pos' => true,
                'products' => true,
                'stockIn' => true,
                'transfer' => true,
                'reports' => true,
                'debts' => true,
                'returns' => true,
                'adjustments' => true,
                'users' => true,
            ],
            default => [
                'pos' => true,
                'debts' => false,
                'returns' => false,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'reports' => false,
                'users' => false,
            ],
        };

        $user = User::create([
            'id' => $userId,
            'name' => $request->name,
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role' => $role,
            'warehouse_id' => $warehouseId,
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
        if ($newRole === 'super_admin' && $user->role !== 'super_admin') {
            return back()->withErrors(['role' => 'Forbidden: The platform Super-Administrator role cannot be assigned. There is exactly one platform root Super Admin defined by system environment.']);
        }
        if ($user->isSuperAdmin() && $newRole !== 'super_admin') {
            return back()->withErrors(['role' => 'Forbidden: The platform root Super-Administrator account cannot be demoted.']);
        }

        $newWarehouseId = null;
        if ($request->filled('warehouse_id')) {
            $wh = Warehouse::find($request->warehouse_id);
            if (!$wh) {
                return back()->withErrors(['warehouse_id' => 'Selected branch location does not exist in your business.']);
            }
            $newWarehouseId = $wh->id;
        }

        $permissions = match($newRole) {
            'cashier' => [
                'pos' => true,
                'debts' => true,
                'returns' => true,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'reports' => false,
                'users' => false,
            ],
            'sales_officer' => [
                'pos' => true,
                'debts' => true,
                'returns' => true,
                'reports' => true,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'users' => false,
            ],
            'storekeeper' => [
                'pos' => false,
                'debts' => false,
                'returns' => false,
                'products' => true,
                'stockIn' => true,
                'transfer' => true,
                'adjustments' => true,
                'reports' => false,
                'users' => false,
            ],
            'branch_manager' => [
                'pos' => true,
                'debts' => true,
                'returns' => true,
                'products' => true,
                'stockIn' => true,
                'transfer' => true,
                'adjustments' => true,
                'reports' => true,
                'users' => false,
            ],
            'viewer', 'executive_readonly' => [
                'pos' => false,
                'debts' => false,
                'returns' => false,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'view_only' => true,
                'reports' => true,
                'users' => false,
            ],
            'admin', 'super_admin' => [
                'pos' => true,
                'products' => true,
                'stockIn' => true,
                'transfer' => true,
                'reports' => true,
                'debts' => true,
                'returns' => true,
                'adjustments' => true,
                'users' => true,
            ],
            default => [
                'pos' => true,
                'debts' => false,
                'returns' => false,
                'products' => false,
                'stockIn' => false,
                'transfer' => false,
                'adjustments' => false,
                'reports' => false,
                'users' => false,
            ],
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
