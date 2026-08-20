<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInstalled
{
    /**
     * Redirect to /install if the app has not been installed yet.
     * Block access to /install once it has been installed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed    = file_exists(storage_path('installed'));
        $isInstaller  = $request->is('install') || $request->is('install/*');

        if (!$installed && !$isInstaller) {
            // App not installed – send user to the installer
            return redirect()->route('installer.welcome');
        }

        if ($installed && $isInstaller) {
            // Already installed – block access to installer
            return redirect('/')->with('info', 'The application is already installed.');
        }

        return $next($request);
    }
}
