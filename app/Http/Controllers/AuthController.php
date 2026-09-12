<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Rules\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
        if (config('saas.enabled')) {
            if (empty($tenantId)) {
                $this->hitRateLimit($request, $email);
                return response()->json(['error' => 'Account is not assigned to an active business tenant.'], 403);
            }

            if ($tenantId !== 'default-tenant') {
                $tenant = Tenant::find($tenantId);
                if (!$tenant) {
                    $this->hitRateLimit($request, $email);
                    return response()->json(['error' => 'Your business account was not found. Please contact support.'], 403);
                }
                if (!$tenant->isActive()) {
                    $this->hitRateLimit($request, $email);
                    return response()->json(['error' => 'Your business subscription has expired or been suspended.'], 403);
                }
            }
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
            return redirect()->route('dashboard');
        }
        return redirect()->route('portal.tenant.login');
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
        if (config('saas.enabled')) {
            if (empty($tenantId)) {
                $this->hitRateLimit($request, $email);
                return back()->withInput()->with('error', 'Account is not assigned to an active business tenant. Please contact support.');
            }

            if ($tenantId !== 'default-tenant') {
                $tenant = Tenant::find($tenantId);
                if (!$tenant) {
                    $this->hitRateLimit($request, $email);
                    return back()->withInput()->with('error', 'Your business account was not found. Please contact support.');
                }
                if (!$tenant->isActive()) {
                    $this->hitRateLimit($request, $email);
                    return back()->withInput()->with('error', 'Your business subscription has expired or been suspended.');
                }
            }
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
        session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id']);
        if (\Illuminate\Support\Facades\Auth::check()) {
            \Illuminate\Support\Facades\Auth::logout();
        }
        return response()->json(['status' => 'logged_out']);
    }

    public function webLogout(Request $request)
    {
        session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id']);
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
        session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'portal']);
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

    /**
     * Display the self-service change password interface for the authenticated user.
     */
    public function showChangePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('warning', 'Please log in to change your password.');
        }

        return view('auth.change-password', [
            'user' => $user,
        ]);
    }

    /**
     * Process a self-service password change for the currently authenticated user.
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login')->with('warning', 'Please log in to change your password.');
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => array_merge(['confirmed'], PasswordPolicy::rules(true)),
        ], PasswordPolicy::messages());

        if (!Hash::check($request->current_password, $user->password)) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'The current password provided does not match our records.'], 422);
            }
            return back()->withErrors(['current_password' => 'The current password provided does not match our records.'])->withInput();
        }

        // Prevent setting identical password
        if (Hash::check($request->new_password, $user->password)) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'New password must be different from your current password.'], 422);
            }
            return back()->withErrors(['new_password' => 'New password must be different from your current password.'])->withInput();
        }

        // Persist new hashed password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Safely re-authenticate session
        Auth::login($user);

        // Security Audit Log (Never log plaintext password or hashes)
        $clientIp = $request->ip() ?? '127.0.0.1';
        Activity::create([
            'id'          => (string) Str::uuid(),
            'tenant_id'   => session('tenant_id') ?? $user->tenant_id ?? null,
            'type'        => 'PASSWORD_CHANGED',
            'description' => "User '{$user->name}' ({$user->email}, Role: {$user->role}) updated account password from IP {$clientIp}.",
            'userId'      => $user->id,
            'userName'    => $user->name,
            'timestamp'   => now()->toIso8601String(),
            'metadata'    => [
                'ip'         => $clientIp,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'user_id'    => $user->id,
                'action'     => 'PASSWORD_CHANGED',
                'tenant_id'  => session('tenant_id') ?? $user->tenant_id,
                'request_id' => $request->header('X-Request-ID') ?? (string) Str::uuid(),
            ],
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Password updated successfully.']);
        }

        return redirect()->route('account.password')->with('success', '✓ Your password has been changed successfully. Your new password is now active.');
    }

    /**
     * Display the tenant / user forgot password request interface.
     */
    public function showForgotPassword(Request $request)
    {
        if (session('user_id') || Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password');
    }

    /**
     * Handle sending a password reset link to the user/tenant email.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->input('email')));

        // Dual-layer Rate Limiter (5 attempts per 15 minutes per email/IP)
        $rateLimitKey = 'forgot-password|' . Str::transliterate($email . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);
            return back()->withInput()->with('error', "Too many password reset requests. Please try again in {$retryAfter} seconds.");
        }
        RateLimiter::hit($rateLimitKey, 900);

        // Pre-auth user resolution (safely bypasses TenantScope)
        $user = User::findForAuthentication($email);

        if (!$user) {
            return back()->withInput()->with('error', "No registered account found with the email address '{$email}'. Please check your spelling or contact support.");
        }

        if ($user->disabled) {
            return back()->withInput()->with('error', 'This account has been deactivated. Please contact your store administrator.');
        }

        // 🔒 Invariant: Password reset recovery is strictly restricted to active business tenants
        if ($user->tenant_id === 'default-tenant' || empty($user->tenant_id)) {
            return back()->withInput()->with('error', 'Platform administrators cannot reset credentials via the tenant portal. Please contact server operations.');
        }

        // Validate that the user belongs to an active tenant
        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant) {
            return back()->withInput()->with('error', 'Your business account was not found. Please contact support.');
        }

        if (!$tenant->isActive()) {
            return back()->withInput()->with('error', 'Your business subscription has expired or been suspended.');
        }

        // Generate cryptographically secure reset token
        $rawToken = Str::random(64);
        $hashedToken = hash('sha256', $rawToken);

        // Store in password_reset_tokens table (upsert)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $hashedToken,
                'created_at' => now(),
            ]
        );

        $resetUrl = url(route('password.reset', ['token' => $rawToken, 'email' => $email]));

        // Attempt sending email via configured mailer
        $mailSent = false;
        $mailError = null;

        try {
            Mail::raw("Hello {$user->name},\n\nYou have requested to reset your password for your account.\nClick the link below to set a new password:\n\n{$resetUrl}\n\nThis link will expire in 60 minutes.\nIf you did not request this, please disregard this email.", function ($message) use ($email, $user) {
                $message->to($email, $user->name)
                        ->subject('Password Reset Request - ' . config('app.name', 'Victorious POS'));
            });
            $mailSent = true;
        } catch (\Throwable $e) {
            $mailError = $e->getMessage();
            Log::warning("Password reset email delivery failed for {$email}: " . $mailError);
        }

        // Secondary Transport: Attempt native PHP mail() if primary mailer did not dispatch
        if (!$mailSent && function_exists('mail')) {
            try {
                $subject = 'Password Reset Request - ' . config('app.name', 'Victorious POS');
                $fromEmail = config('mail.from.address') ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'pos.victoriousmarket.com.ng');
                $fromName = config('mail.from.name') ?: config('app.name', 'Victorious POS');
                $headers = [
                    'From: ' . $fromName . ' <' . $fromEmail . '>',
                    'Reply-To: ' . $fromEmail,
                    'X-Mailer: PHP/' . phpversion(),
                    'MIME-Version: 1.0',
                    'Content-Type: text/plain; charset=UTF-8',
                ];
                $body = "Hello {$user->name},\n\nYou have requested to reset your password for your account.\nClick the link below to set a new password:\n\n{$resetUrl}\n\nThis link will expire in 60 minutes.\nIf you did not request this, please disregard this email.";

                if (@mail($email, $subject, $body, implode("\r\n", $headers))) {
                    $mailSent = true;
                    Log::info("Password reset email sent successfully via native PHP mail() to {$email}");
                }
            } catch (\Throwable $ex) {
                Log::warning("Native PHP mail() fallback failed for {$email}: " . $ex->getMessage());
            }
        }

        // High Security Audit Logging: Log event without exposing raw token URL
        Log::info("Password reset token generated for [{$email}]. Delivery status: " . ($mailSent ? 'DISPATCHED' : 'FAILED'));

        if ($mailSent) {
            return back()->with('status', "✓ Password reset instructions have been sent to {$email}. Please check your email inbox and spam folder.");
        }

        return back()->with('error', "⚠️ We were unable to dispatch the password reset email. Please contact your system administrator or support.");
    }

    /**
     * Display the reset password interface for a verified token.
     */
    public function showResetPassword(Request $request, string $token)
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $hashedToken = hash('sha256', $token);

        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$tokenRecord || !hash_equals($tokenRecord->token, $hashedToken)) {
            // Also attempt fallback query by token directly if email param is omitted
            $tokenRecord = DB::table('password_reset_tokens')
                ->where('token', $hashedToken)
                ->first();
            if ($tokenRecord) {
                $email = $tokenRecord->email;
            }
        }

        if (!$tokenRecord || !hash_equals($tokenRecord->token, $hashedToken)) {
            return redirect()->route('password.request')->with('error', 'The password reset token is invalid. Please request a new reset link.');
        }

        if (Carbon::parse($tokenRecord->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return redirect()->route('password.request')->with('error', 'This password reset link has expired. Please request a new one.');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Process password reset and update the user's credentials.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => array_merge(['confirmed'], PasswordPolicy::rules(true)),
        ], PasswordPolicy::messages());

        $email = strtolower(trim($request->input('email')));
        $token = (string) $request->input('token');
        $hashedToken = hash('sha256', $token);

        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$tokenRecord || !hash_equals($tokenRecord->token, $hashedToken)) {
            return back()->withErrors(['email' => 'This password reset token is invalid or has already been used.'])->withInput();
        }

        if (Carbon::parse($tokenRecord->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return redirect()->route('password.request')->with('error', 'This password reset link has expired. Please request a new one.');
        }

        // Retrieve user via authentication lookup (safe pre-auth bypass of TenantScope)
        $user = User::findForAuthentication($email);

        if (!$user) {
            return back()->withErrors(['email' => 'We could not find a user account matching this email address.'])->withInput();
        }

        if ($user->disabled) {
            return back()->withErrors(['email' => 'This account is currently disabled. Please contact the store administrator.'])->withInput();
        }

        // 🔒 Invariant: Password reset recovery is strictly restricted to active business tenants
        if ($user->tenant_id === 'default-tenant' || empty($user->tenant_id)) {
            return back()->withErrors(['email' => 'Platform administrator credentials cannot be reset via the tenant portal.'])->withInput();
        }

        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant || !$tenant->isActive()) {
            return back()->withErrors(['email' => 'Your business subscription has expired or been suspended.'])->withInput();
        }

        // Update password
        $user->password = Hash::make($request->input('password'));
        $user->save();

        // Invalidate used reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Security Audit Log
        $clientIp = $request->ip() ?? '127.0.0.1';
        Activity::create([
            'id'          => (string) Str::uuid(),
            'tenant_id'   => $user->tenant_id,
            'type'        => 'PASSWORD_RESET',
            'description' => "User '{$user->name}' ({$user->email}) reset password via self-service email token recovery.",
            'userId'      => $user->id,
            'userName'    => $user->name,
            'timestamp'   => now()->toIso8601String(),
            'metadata'    => [
                'ip'         => $clientIp,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'action'     => 'PASSWORD_RESET',
                'tenant_id'  => $user->tenant_id,
                'user_id'    => $user->id,
            ],
        ]);

        return redirect()->route('portal.tenant.login')->with('success', '✓ Your password has been successfully reset! You can now log in with your new password.');
    }
}

