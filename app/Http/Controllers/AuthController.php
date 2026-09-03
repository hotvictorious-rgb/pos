<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        
        if (!$email || !$password) {
            return response()->json(['error' => 'Email address and password are required.'], 400);
        }

        $user = User::findForAuthentication($email);

        if (!$user) {
            return response()->json(['error' => 'Invalid email address or password.'], 401);
        }

        if ($user->disabled) {
            return response()->json(['error' => 'Your account has been disabled by the administrator.'], 403);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Invalid email address or password.'], 401);
        }

        $tenantId = $user->tenant_id;
        if (config('saas.enabled') && empty($tenantId)) {
            return response()->json(['error' => 'Account is not assigned to an active business tenant.'], 403);
        }

        $activeTenantId = $tenantId ?: 'default-tenant';

        $request->session()->regenerate();
        session([
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'tenant_id' => $activeTenantId
        ]);
        \Illuminate\Support\Facades\Auth::login($user);

        return response()->json($user);
    }

    public function showLogin(Request $request)
    {
        if (session('user_id') || \Illuminate\Support\Facades\Auth::check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    public function webLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::findForAuthentication($email);

        if (!$user) {
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        if ($user->disabled) {
            return back()->withInput()->with('error', 'Your account has been disabled by the administrator.');
        }

        if (!Hash::check($password, $user->password)) {
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        $tenantId = $user->tenant_id;
        if (config('saas.enabled') && empty($tenantId)) {
            return back()->withInput()->with('error', 'Account is not assigned to an active business tenant. Please contact support.');
        }

        $activeTenantId = $tenantId ?: 'default-tenant';

        $request->session()->regenerate();
        session([
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'tenant_id' => $activeTenantId
        ]);
        \Illuminate\Support\Facades\Auth::login($user);

        $intended = session()->pull('url.intended', '/');
        return redirect($intended)->with('success', "Welcome back, {$user->name}!");
    }

    public function logout()
    {
        session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'is_impersonating', 'impersonator_id']);
        if (\Illuminate\Support\Facades\Auth::check()) {
            \Illuminate\Support\Facades\Auth::logout();
        }
        return response()->json(['status' => 'logged_out']);
    }

    public function webLogout(Request $request)
    {
        session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'is_impersonating', 'impersonator_id']);
        if (\Illuminate\Support\Facades\Auth::check()) {
            \Illuminate\Support\Facades\Auth::logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', '✓ You have been logged out successfully.');
    }

    public function me()
    {
        $userId = session('user_id');
        if ($userId) {
            $user = User::find($userId);
            if ($user && !$user->disabled) {
                return response()->json($user);
            }
        }
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
