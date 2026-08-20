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
            'admin' => ['all' => true],
            'manager' => ['pos' => true, 'stockIn' => true, 'transfer' => true, 'reports' => true],
            'storekeeper' => ['stockIn' => true, 'transfer' => true, 'count' => true],
            'cashier' => ['pos' => true, 'debts' => true],
            default => ['pos' => true],
        };

        $user = User::create([
            'id' => $userId,
            'name' => $request->name,
            'email' => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role' => $role,
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
