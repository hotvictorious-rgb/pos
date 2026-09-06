<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;
use App\Exceptions\SecurityException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SaasProvisionRootCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:provision-root
                            {--email= : The email address for the platform root user}
                            {--password= : Explicit plaintext password to set/replace}
                            {--password-hash= : Pre-hashed password (for secure installer delegation)}
                            {--force : Allow provisioning despite existing state without resetting password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision and verify the SaaS master platform tenant and root Super Admin identity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $superAdminEmail = strtolower(trim(
            $this->option('email') ?: (config('saas.super_admin_email') ?: env('SUPER_ADMIN_EMAIL', 'superadmin@hysam.com'))
        ));

        if (!filter_var($superAdminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address provided for platform root: '{$superAdminEmail}'");
            return Command::FAILURE;
        }

        // 1. Detect Cross-Tenant Identity Collision (Fail-Closed)
        $existingUser = User::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [$superAdminEmail])
            ->first();

        if ($existingUser && $existingUser->tenant_id !== 'default-tenant') {
            $msg = "Identity Conflict: User {$superAdminEmail} belongs to tenant '{$existingUser->tenant_id}', not 'default-tenant'. Automatic migration is forbidden. Manual intervention required.";
            $this->error($msg);
            throw new SecurityException($msg);
        }

        // 2. Ensure Master Platform Tenant (default-tenant)
        $defaultTenant = Tenant::withoutGlobalScopes()->find('default-tenant');
        if (!$defaultTenant) {
            $defaultTenant = Tenant::create([
                'id' => 'default-tenant',
                'name' => 'Platform Master HQ',
                'owner_email' => $superAdminEmail,
                'owner_phone' => '08000000000',
                'plan' => 'enterprise',
                'status' => 'active',
                'max_branches' => 999,
                'max_users' => 999,
            ]);
            $this->info("Created platform master tenant: 'default-tenant'.");
        } else {
            $this->line("Platform master tenant 'default-tenant' verified.");
        }

        // 3. Provision or Invariant-Enforce Root User
        $knownWeakPasswords = [
            'changeme123',
            'admin123',
            'staff123',
            'password',
            '12345678',
            'secret',
            'set_your_secure_password_here',
            'your_cpanel_db_password',
            'your_email_password',
        ];

        $isWeakOrPlaceholder = function (?string $pw) use ($knownWeakPasswords): bool {
            if (empty($pw)) {
                return true;
            }
            $clean = strtolower(trim($pw));
            return in_array($clean, $knownWeakPasswords, true)
                || str_contains($clean, 'set_your_')
                || str_contains($clean, 'your_password')
                || str_contains($clean, 'placeholder');
        };

        if ($existingUser) {
            // INVARIANT: When root user already exists, re-execution requires explicit --force or explicit rotation
            if (!$this->option('force') && !$this->option('password') && !$this->option('password-hash')) {
                $this->error("Platform root user '{$superAdminEmail}' already exists. Re-provisioning requires explicit --force flag or explicit rotation option.");
                return Command::FAILURE;
            }

            // INVARIANT: Do NOT reset existing password unless explicitly supplied via --password or --password-hash
            if ($this->option('password')) {
                $rawPw = $this->option('password');
                if (app()->environment('production') && $isWeakOrPlaceholder($rawPw)) {
                    throw new SecurityException("Security Violation: Production password change requires a secure, non-default, non-placeholder password.");
                }
                $existingUser->password = Hash::make($rawPw);
                $this->info("Updated root password from explicit --password option.");
            } elseif ($this->option('password-hash')) {
                $existingUser->password = $this->option('password-hash');
                $this->info("Updated root password from explicit pre-hashed credential.");
            } else {
                $this->line("Preserving existing root user password (no explicit --password provided).");
            }

            $existingUser->tenant_id = 'default-tenant';
            $existingUser->role = 'admin';
            $existingUser->disabled = false;
            $existingUser->permissions = json_encode(['all' => true]);
            $existingUser->save();

            $rootUser = $existingUser;
        } else {
            // Fresh root creation: determine password
            $hashedPassword = null;

            if ($this->option('password-hash')) {
                $hashedPassword = $this->option('password-hash');
            } else {
                $rawPw = $this->option('password')
                    ?: (app()->environment('testing') ? 'test-super-secret-pw' : (env('SUPER_ADMIN_PASSWORD') ?: null));

                if (empty($rawPw)) {
                    if (app()->environment('production')) {
                        throw new SecurityException("Security Violation: Production root provisioning requires an explicit password or SUPER_ADMIN_PASSWORD environment variable.");
                    }
                    $rawPw = Str::random(32);
                    $this->warn("Generated random password for Super Admin: {$rawPw}");
                }

                if (app()->environment('production') && $isWeakOrPlaceholder($rawPw)) {
                    throw new SecurityException("Security Violation: Production root provisioning requires a secure, non-default, non-placeholder password.");
                }

                $hashedPassword = Hash::make($rawPw);
            }

            $rootUser = User::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'email' => $superAdminEmail,
                'password' => $hashedPassword,
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]);
            $this->info("Created platform Super Admin: {$superAdminEmail}");
        }

        // 4. Invariant Assertion
        $rootUser->refresh();
        if (!$rootUser->isPlatformAdmin()) {
            $msg = "Verification Failed: Provisioned user '{$superAdminEmail}' does not satisfy isPlatformAdmin().";
            $this->error($msg);
            throw new SecurityException($msg);
        }

        $this->info("Platform root user verified: {$superAdminEmail} satisfies isPlatformAdmin().");
        return Command::SUCCESS;
    }
}
