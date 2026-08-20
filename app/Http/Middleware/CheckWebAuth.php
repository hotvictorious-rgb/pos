<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckWebAuth
{
    /**
     * Ensure the user is logged into the web session before accessing protected pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicPaths = [
            'login',
            'logout',
            'install',
            'install/*',
            'api/*',
            'up',
        ];

        foreach ($publicPaths as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $userId = session('user_id');

        if (!$userId && !Auth::check()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            session()->put('url.intended', $request->fullUrl());
            return redirect()->route('login')->with('warning', 'Please log in to access the system.');
        }

        $user = Auth::user() ?: User::find($userId);

        if (!$user || $user->disabled) {
            session()->forget('user_id');
            Auth::logout();
            return redirect()->route('login')->with('error', 'Your session has expired or your account is disabled.');
        }

        if (!Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user);
        }

        view()->share('authUser', $user);

        return $next($request);
    }
}
