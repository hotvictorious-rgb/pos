<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Rules\PasswordPolicy;

class InstallerController extends Controller
{
    /** Step 1 – Welcome */
    public function welcome()
    {
        return view('installer.welcome');
    }

    /** Step 2 – Requirements check */
    public function requirements()
    {
        $requirements = $this->checkRequirements();
        $allPassed    = !in_array(false, array_column($requirements, 'passed'));

        return view('installer.requirements', compact('requirements', 'allPassed'));
    }

    /** Step 3 – Database configuration form */
    public function database()
    {
        return view('installer.database');
    }

    /** Step 3 – Process database form & write .env */
    public function databaseSave(Request $request)
    {
        $request->validate([
            'db_host'     => 'required|string',
            'db_port'     => 'required|numeric',
            'db_name'     => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        // Test connection before saving
        try {
            $pdo = new \PDO(
                "mysql:host={$request->db_host};port={$request->db_port};dbname={$request->db_name}",
                $request->db_username,
                $request->db_password ?? ''
            );
        } catch (\PDOException $e) {
            \Log::error("Installer DB connection error: " . $e->getMessage());
            return back()->withErrors(['db_host' => 'Unable to connect to the database. Please verify your host, port, credentials, and database name.'])
                         ->withInput();
        }

        // Write the .env file
        $this->writeEnv([
            'APP_NAME'     => '"Hysam Ventures"',
            'APP_ENV'      => 'production',
            'APP_DEBUG'    => 'false',
            'APP_URL'      => $request->getSchemeAndHttpHost(),
            'DB_CONNECTION'=> 'mysql',
            'DB_HOST'      => $request->db_host,
            'DB_PORT'      => $request->db_port,
            'DB_DATABASE'  => $request->db_name,
            'DB_USERNAME'  => $request->db_username,
            'DB_PASSWORD'  => $request->db_password ?? '',
            'CACHE_STORE'  => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER'   => 'file',
        ]);

        Artisan::call('config:clear');

        return redirect()->route('installer.admin');
    }

    /** Step 4 – Admin account form */
    public function admin()
    {
        return view('installer.admin');
    }

    /** Step 4 – Process admin form & run migrations */
    public function install(Request $request)
    {
        $request->validate([
            'admin_name'     => 'required|string|max:100',
            'admin_email'    => 'required|email',
            'admin_password' => array_merge(['confirmed'], PasswordPolicy::rules(true)),
        ], PasswordPolicy::messages());

        // Store pre-hashed password in session; never store plaintext passwords
        session([
            'installer_admin_name'          => $request->admin_name,
            'installer_admin_email'         => $request->admin_email,
            'installer_admin_password_hash' => Hash::make($request->admin_password),
        ]);

        return view('installer.installing');
    }

    /** Step 5 – AJAX: run migrations and create admin */
    public function run()
    {
        try {
            // Standalone installer takeover protection (Fail-Closed)
            // If database is already migrated and users exist, reject takeover
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && \App\Models\User::withoutGlobalScopes()->exists()) {
                abort(403, 'Security Violation: Cannot run installer when user accounts already exist.');
            }

            // Generate app key if not set
            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Re-verify after migrations that no accounts existed
            if (\App\Models\User::withoutGlobalScopes()->exists()) {
                abort(403, 'Security Violation: Cannot provision initial admin over existing users.');
            }

            // Create initial admin user
            $adminName     = session('installer_admin_name');
            $adminEmail    = session('installer_admin_email');
            $adminPassHash = session('installer_admin_password_hash');

            if ($adminEmail && $adminPassHash) {
                if (config('saas.enabled')) {
                    // Temporarily align super_admin_email config for this installation run if needed
                    config(['saas.super_admin_email' => $adminEmail]);

                    Artisan::call('saas:provision-root', [
                        '--email' => $adminEmail,
                        '--password-hash' => $adminPassHash,
                        '--force' => true,
                    ]);

                    $root = \App\Models\User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [strtolower(trim($adminEmail))])->first();
                    if (!$root || !$root->isPlatformAdmin()) {
                        throw new \App\Exceptions\SecurityException("Security Invariant Failed: Initial root provisioning did not satisfy isPlatformAdmin().");
                    }
                } else {
                    \App\Models\User::create([
                        'id'       => (string) \Illuminate\Support\Str::uuid(),
                        'name'     => $adminName,
                        'email'    => $adminEmail,
                        'password' => $adminPassHash,
                        'role'     => 'admin',
                    ]);
                }
            }

            // Write the installed lock file
            file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

            // Compile caches only outside testing environments
            if (!app()->environment('testing')) {
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');
            }

            // Clear installer session data immediately
            session()->forget(['installer_admin_name', 'installer_admin_email', 'installer_admin_password_hash']);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Step 6 – Complete */
    public function complete()
    {
        return view('installer.complete');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Build the requirements list */
    private function checkRequirements(): array
    {
        return [
            ['name' => 'PHP >= 8.1',              'passed' => version_compare(PHP_VERSION, '8.1.0', '>=')],
            ['name' => 'PDO Extension',            'passed' => extension_loaded('pdo')],
            ['name' => 'PDO MySQL Extension',      'passed' => extension_loaded('pdo_mysql')],
            ['name' => 'Mbstring Extension',       'passed' => extension_loaded('mbstring')],
            ['name' => 'OpenSSL Extension',        'passed' => extension_loaded('openssl')],
            ['name' => 'Tokenizer Extension',      'passed' => extension_loaded('tokenizer')],
            ['name' => 'XML Extension',            'passed' => extension_loaded('xml')],
            ['name' => 'cURL Extension',           'passed' => extension_loaded('curl')],
            ['name' => 'BCMath Extension',         'passed' => extension_loaded('bcmath')],
            ['name' => 'storage/ Writable',        'passed' => is_writable(storage_path())],
            ['name' => 'bootstrap/cache Writable', 'passed' => is_writable(base_path('bootstrap/cache'))],
        ];
    }

    /** Write key=value pairs to the .env file */
    private function writeEnv(array $data): void
    {
        $envPath = base_path('.env');

        // Start from .env.example if .env doesn't exist yet
        if (!file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $env = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $line    = "{$key}={$value}";

            if (preg_match($pattern, $env)) {
                $env = preg_replace($pattern, $line, $env);
            } else {
                $env .= "\n{$line}";
            }
        }

        file_put_contents($envPath, $env);
    }
}
