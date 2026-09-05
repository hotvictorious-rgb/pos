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
        $installedMarker   = file_exists(storage_path('installed'));
        $installedConfig   = (bool) config('app.installed', false);
        $installed         = $installedMarker || $installedConfig;

        // In production, the installer is disabled by default unless explicitly permitted via config('app.installer_enabled')
        $installerEnabled  = (bool) config('app.installer_enabled', !app()->environment('production'));
        $isInstaller       = $request->is('install') || $request->is('install/*');

        // Fail-Closed Guard 1: Any attempt to reach the installer when already installed
        if ($installed && $isInstaller) {
            return redirect('/')->with('info', 'The application is already installed.');
        }

        // Fail-Closed Guard 2: Any attempt to reach the installer when explicitly disabled in production
        if ($isInstaller && !$installerEnabled) {
            abort(403, 'Web installer wizard is disabled in this environment for security.');
        }

        // Redirect uninstalled instances to the wizard ONLY if installer is enabled
        if (!$installed && !$isInstaller) {
            if ($installerEnabled) {
                return redirect()->route('installer.welcome');
            }
            // If installer disabled and no marker, assume manual/API deployment
            return $next($request);
        }

        return $next($request);
    }
}
