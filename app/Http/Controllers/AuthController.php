<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');
        
        if (!$email || !$password) {
            return response()->json(['error' => 'Email address and password are required.'], 400);
        }

        if ($retryAfter = $this->checkRateLimit($request, $email)) {
            return response()->json(['error' => "Too many login attempts. Please try again in {$retryAfter} seconds."], 429);
        }

        $user = User::findForAuthentication($email);

        if (!$user) {
            $this->hitRateLimit($request, $email);
            return response()->json(['error' => 'Invalid email address or password.'], 401);
        }

        if ($user->disabled) {
            $this->hitRateLimit($request, $email);
            return response()->json(['error' => 'Your account has been disabled by the administrator.'], 403);
        }

        if (!Hash::check($password, $user->password)) {
            $this->hitRateLimit($request, $email);
            return response()->json(['error' => 'Invalid email address or password.'], 401);
        }

        $tenantId = $user->tenant_id;
        if (config('saas.enabled') && empty($tenantId)) {
            $this->hitRateLimit($request, $email);
            return response()->json(['error' => 'Account is not assigned to an active business tenant.'], 403);
        }

        $this->clearRateLimit($request, $email);

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

        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        if ($retryAfter = $this->checkRateLimit($request, $email)) {
            return back()->withInput()->with('error', "Too many login attempts. Please try again in {$retryAfter} seconds.");
        }

        $user = User::findForAuthentication($email);

        if (!$user) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        if ($user->disabled) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Your account has been disabled by the administrator.');
        }

        if (!Hash::check($password, $user->password)) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        $tenantId = $user->tenant_id;
        if (config('saas.enabled') && empty($tenantId)) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Account is not assigned to an active business tenant. Please contact support.');
        }

        $this->clearRateLimit($request, $email);

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
        if (!$user->isSuperAdmin() && (str_contains($intended, 'saas/admin') || str_contains($intended, 'super-admin'))) {
            $intended = '/';
        }
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

    /**
     * Display the portal-specific login interface.
     */
    public function showPortalLogin(Request $request, string $portal)
    {
        $allowedPortals = ['tenant', 'tenant-employee', 'super-admin', 'super-admin-employee'];
        if (!in_array($portal, $allowedPortals)) {
            abort(404, 'Portal not found.');
        }

        if (session('user_id') || Auth::check()) {
            $user = Auth::user();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return redirect()->route('saas.admin.index');
            }
            return redirect('/');
        }

        $meta = match($portal) {
            'tenant' => [
                'portal'      => 'tenant',
                'badge'       => '🏢 TENANT ADMIN PORTAL',
                'badge_color' => '#f59e0b',
                'title'       => 'Business Owner Sign In',
                'subtitle'    => 'Business administration, consolidated analytics & shop branches',
                'route'       => route('portal.tenant.login.post'),
                'icon'        => '🏢',
            ],
            'tenant-employee' => [
                'portal'      => 'tenant-employee',
                'badge'       => '💼 EMPLOYEE PORTAL',
                'badge_color' => '#10b981',
                'title'       => 'Staff & Cashier Sign In',
                'subtitle'    => 'Daily POS checkout, branch sales, and shelf inventory',
                'route'       => route('portal.tenant_employee.login.post'),
                'icon'        => '💼',
            ],
            'super-admin' => [
                'portal'      => 'super-admin',
                'badge'       => '🛡️ SUPER-ADMIN CONSOLE',
                'badge_color' => '#ef4444',
                'title'       => 'Platform Super-Admin Sign In',
                'subtitle'    => 'Platform tenant oversight, subscriptions & master control',
                'route'       => route('portal.super_admin.login.post'),
                'icon'        => '🛡️',
            ],
            'super-admin-employee' => [
                'portal'      => 'super-admin-employee',
                'badge'       => '👥 PLATFORM STAFF PORTAL',
                'badge_color' => '#6366f1',
                'title'       => 'Platform Employee Sign In',
                'subtitle'    => 'Platform operations, system auditing & technical support',
                'route'       => route('portal.super_admin_employee.login.post'),
                'icon'        => '👥',
            ],
        };

        return view('auth.portal-login', compact('meta'));
    }

    /**
     * Process authentication for a specific portal under strict server-side RBAC validation.
     */
    public function portalLogin(Request $request, string $portal)
    {
        $allowedPortals = ['tenant', 'tenant-employee', 'super-admin', 'super-admin-employee'];
        if (!in_array($portal, $allowedPortals)) {
            abort(404, 'Portal not found.');
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        if ($retryAfter = $this->checkRateLimit($request, $email)) {
            return back()->withInput()->with('error', "Too many login attempts. Please try again in {$retryAfter} seconds.");
        }

        // 1. Identity Resolution (pre-auth lookup without global scopes)
        $user = User::findForAuthentication($email);

        if (!$user) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        // 2. Disabled Account Check
        if ($user->disabled) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Your account has been disabled by the administrator.');
        }

        // 3. Password Verification
        if (!Hash::check($password, $user->password)) {
            $this->hitRateLimit($request, $email);
            return back()->withInput()->with('error', 'Invalid email address or password.');
        }

        // 4. Strict Portal-Account Category Verification (LOGIN URL != PERMISSION)
        if (config('saas.enabled')) {
            switch ($portal) {
                case 'super-admin':
                    if (!$user->isSuperAdmin()) {
                        $this->hitRateLimit($request, $email);
                        if ($user->tenant_id === 'default-tenant') {
                            return back()->withInput()->with('error', 'Super-Administrator authority required. Platform staff must log in through the Super-Admin Employee Portal.');
                        }
                        return back()->withInput()->with('error', 'Access Denied: Customer business accounts cannot authenticate through the Super-Admin Portal.');
                    }
                    break;

                case 'super-admin-employee':
                    if (!$user->isSuperAdminEmployee()) {
                        $this->hitRateLimit($request, $email);
                        if ($user->isSuperAdmin()) {
                            return back()->withInput()->with('error', 'Super-Administrators must log in through the primary Super-Admin Portal.');
                        }
                        return back()->withInput()->with('error', 'Access Denied: Customer business accounts cannot authenticate through the Platform Employee Portal.');
                    }
                    break;

                case 'tenant':
                    if (!$user->isTenantAdmin()) {
                        $this->hitRateLimit($request, $email);
                        if ($user->tenant_id === 'default-tenant') {
                            return back()->withInput()->with('error', 'This portal is for Business Tenant Owners only. Platform administrators must log in through the Super-Admin Portal.');
                        }
                        if (empty($user->tenant_id)) {
                            return back()->withInput()->with('error', 'Account is not assigned to an active business tenant. Please contact support.');
                        }
                        return back()->withInput()->with('error', 'This portal is restricted to Business Owners and Administrators. Staff and cashiers must log in through the Tenant Employee Portal.');
                    }

                    // Validate tenant status
                    $tenant = Tenant::find($user->tenant_id);
                    if (!$tenant) {
                        $this->hitRateLimit($request, $email);
                        return back()->withInput()->with('error', 'Your business account was not found. Please contact support.');
                    }
                    if (!$tenant->isActive()) {
                        $this->hitRateLimit($request, $email);
                        return back()->withInput()->with('error', 'Your business subscription has expired or been suspended.');
                    }
                    break;

                case 'tenant-employee':
                    if (!$user->isTenantEmployee()) {
                        $this->hitRateLimit($request, $email);
                        if ($user->tenant_id === 'default-tenant') {
                            return back()->withInput()->with('error', 'Platform accounts are not authorized to use the Tenant Employee Portal.');
                        }
                        if (empty($user->tenant_id)) {
                            return back()->withInput()->with('error', 'Account is not assigned to an active business tenant. Please contact support.');
                        }
                        return back()->withInput()->with('error', 'Business owners and administrators must log in through the Tenant Portal.');
                    }

                    // Validate tenant status
                    $tenant = Tenant::find($user->tenant_id);
                    if (!$tenant) {
                        $this->hitRateLimit($request, $email);
                        return back()->withInput()->with('error', 'Your business account was not found. Please contact support.');
                    }
                    if (!$tenant->isActive()) {
                        $this->hitRateLimit($request, $email);
                        return back()->withInput()->with('error', 'Your business subscription has expired or been suspended.');
                    }
                    break;
            }
        }

        // Clear rate limiter on successful authentication
        $this->clearRateLimit($request, $email);

        // 5. Session Setup & Regeneration
        $activeTenantId = $user->tenant_id ?: 'default-tenant';

        $request->session()->regenerate();
        session([
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'tenant_id' => $activeTenantId,
            'portal'    => $portal,
        ]);
        Auth::login($user);

        // 6. Navigation
        if ($portal === 'super-admin' || $portal === 'super-admin-employee') {
            session()->forget('url.intended');
            return redirect()->route('saas.admin.index')->with('success', "Welcome back, {$user->name}!");
        }

        $intended = session()->pull('url.intended', '/');
        if (str_contains($intended, 'saas/admin') || str_contains($intended, 'super-admin')) {
            $intended = '/';
        }
        return redirect($intended)->with('success', "Welcome back, {$user->name}!");
    }

    /**
     * Log out from a specific portal.
     */
    public function portalLogout(Request $request, string $portal)
    {
        session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'is_impersonating', 'impersonator_id', 'portal']);
        if (Auth::check()) {
            Auth::logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectRoute = match($portal) {
            'tenant-employee'      => route('portal.tenant_employee.login'),
            'super-admin'          => route('portal.super_admin.login'),
            'super-admin-employee' => route('portal.super_admin_employee.login'),
            default                => route('portal.tenant.login'),
        };

        return redirect($redirectRoute)->with('success', '✓ You have been logged out successfully.');
    }

    /**
     * Check if the authentication attempt is rate-limited.
     * Dual-layer: 5 attempts/minute per account+IP, 20 attempts/minute globally per IP.
     */
    protected function checkRateLimit(Request $request, string $email): ?int
    {
        $emailKey = Str::transliterate(Str::lower($email) . '|' . $request->ip());
        $ipKey = Str::transliterate('login-ip|' . $request->ip());

        if (RateLimiter::tooManyAttempts($emailKey, 5)) {
            return RateLimiter::availableIn($emailKey);
        }

        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            return RateLimiter::availableIn($ipKey);
        }

        return null;
    }

    /**
     * Record a failed authentication attempt against rate limiters.
     */
    protected function hitRateLimit(Request $request, string $email): void
    {
        $emailKey = Str::transliterate(Str::lower($email) . '|' . $request->ip());
        $ipKey = Str::transliterate('login-ip|' . $request->ip());

        RateLimiter::hit($emailKey, 60);
        RateLimiter::hit($ipKey, 60);
    }

    /**
     * Clear rate limiter records on successful authentication.
     */
    protected function clearRateLimit(Request $request, string $email): void
    {
        $emailKey = Str::transliterate(Str::lower($email) . '|' . $request->ip());
        $ipKey = Str::transliterate('login-ip|' . $request->ip());

        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);
    }
}
