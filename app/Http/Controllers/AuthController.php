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

        $normalizedEmail = strtolower(trim($email));
        $adminEmail = strtolower(trim(env('ADMIN_EMAIL', 'admin@hysam.com')));
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        // 1. If it's the configured admin email
        if ($normalizedEmail === $adminEmail) {
            if (!$user) {
                // Auto-create the configured admin if not present
                $user = User::create([
                    'id' => 'admin-user-1',
                    'name' => 'Admin User',
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'disabled' => false,
                    'permissions' => [
                        'create' => true,
                        'edit' => true,
                        'delete' => true,
                        'stockIn' => true,
                        'stockOut' => true
                    ]
                ]);
            }

            if ($user->disabled) {
                return response()->json(['error' => 'Your account has been disabled by the administrator.'], 403);
            }

            if (!Hash::check($password, $user->password) && $password !== $adminPassword) {
                return response()->json(['error' => 'Invalid email address or password.'], 401);
            }

            // Sync database password if it changed in env
            if ($password === $adminPassword && !Hash::check($password, $user->password)) {
                $user->password = Hash::make($adminPassword);
                $user->save();
            }

            session(['user_id' => $user->id]);
            return response()->json($user);
        }

        // 2. For any other user
        if (!$user) {
            return response()->json(['error' => 'This account does not exist. Please contact your administrator.'], 401);
        }

        if ($user->disabled) {
            return response()->json(['error' => 'Your account has been disabled by the administrator.'], 403);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json(['error' => 'Invalid email address or password.'], 401);
        }

        session(['user_id' => $user->id]);
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

        $email = strtolower(trim($request->input('email')));
        $password = $request->input('password');

        $adminEmail = strtolower(trim(env('ADMIN_EMAIL', 'admin@hysam.com')));
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // 1. Auto-seed / verify admin
        if ($email === $adminEmail) {
            if (!$user) {
                $user = User::create([
                    'id' => 'admin-user-1',
                    'name' => 'Admin User',
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'disabled' => false,
                    'permissions' => [
                        'create' => true,
                        'edit' => true,
                        'delete' => true,
                        'stockIn' => true,
                        'stockOut' => true
                    ]
                ]);
            }

            if ($user->disabled) {
                return back()->withInput()->with('error', 'Your account has been disabled by the administrator.');
            }

            if (!Hash::check($password, $user->password) && $password !== $adminPassword) {
                return back()->withInput()->with('error', 'Invalid email address or password.');
            }

            if ($password === $adminPassword && !Hash::check($password, $user->password)) {
                $user->password = Hash::make($adminPassword);
                $user->save();
            }

            session(['user_id' => $user->id, 'user_name' => $user->name, 'user_role' => $user->role]);
            \Illuminate\Support\Facades\Auth::login($user);

            $intended = session()->pull('url.intended', '/');
            return redirect($intended)->with('success', "Welcome back, {$user->name}!");
        }

        // 2. Regular worker login
        if (!$user) {
            return back()->withInput()->with('error', 'This account does not exist. Please contact your administrator.');
        }

        if ($user->disabled) {
            return back()->withInput()->with('error', 'Your account has been disabled by the administrator.');
        }

        if (!Hash::check($password, $user->password)) {
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        session(['user_id' => $user->id, 'user_name' => $user->name, 'user_role' => $user->role]);
        \Illuminate\Support\Facades\Auth::login($user);

        $intended = session()->pull('url.intended', '/');
        return redirect($intended)->with('success', "Welcome back, {$user->name}!");
    }

    public function logout()
    {
        session()->forget(['user_id', 'user_name', 'user_role']);
        return response()->json(['status' => 'logged_out']);
    }

    public function webLogout(Request $request)
    {
        session()->forget(['user_id', 'user_name', 'user_role']);
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
