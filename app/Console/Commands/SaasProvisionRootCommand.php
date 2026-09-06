<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;
use App\Exceptions\SecurityException;
use Illuminate\Support\Facades\DB;
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

        // 2. Pre-Validation Phase (Zero Database Writes before validation passes)
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

        $targetHashedPassword = null;

        if ($existingUser) {
            // INVARIANT: When root user already exists, re-execution requires explicit --force or explicit rotation
            if (!$this->option('force') && !$this->option('password') && !$this->option('password-hash')) {
                $this->error("Platform root user '{$superAdminEmail}' already exists. Re-provisioning requires explicit --force flag or explicit rotation option.");
                return Command::FAILURE;
            }

            if ($this->option('password-hash')) {
                $hash = (string) $this->option('password-hash');
                // Enforce valid bcrypt ($2y$, $2a$, $2b$) or argon2 hash structure for privileged internal interface
                if (!preg_match('/^\$2[ayb]\$[0-9]{2}\$[A-Za-z0-9\.\/]{53}$/', $hash) && !str_starts_with($hash, '$argon2')) {
                    throw new SecurityException("Security Violation: Invalid password hash format provided to internal provisioning interface.");
                }
                $targetHashedPassword = $hash;
                $this->info("Prepared root password update from explicit pre-hashed credential.");
            } elseif ($this->option('password')) {
                $rawPw = (string) $this->option('password');
                if (app()->environment('production') && $isWeakOrPlaceholder($rawPw)) {
                    throw new SecurityException("Security Violation: Production password change requires a secure, non-default, non-placeholder password.");
                }
                $targetHashedPassword = Hash::make($rawPw);
                $this->info("Prepared root password update from explicit --password option.");
            } else {
                $this->line("Preserving existing root user password (no explicit rotation credential provided).");
            }
        } else {
            // Fresh root creation: determine and validate credentials BEFORE creating default-tenant
            if ($this->option('password-hash')) {
                $hash = (string) $this->option('password-hash');
                if (!preg_match('/^\$2[ayb]\$[0-9]{2}\$[A-Za-z0-9\.\/]{53}$/', $hash) && !str_starts_with($hash, '$argon2')) {
                    throw new SecurityException("Security Violation: Invalid password hash format provided to internal provisioning interface.");
                }
                $targetHashedPassword = $hash;
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

                $targetHashedPassword = Hash::make($rawPw);
            }
        }

        // 3. Transactional Provisioning (Atomically create/verify default-tenant + root user)
        $isFresh = !$existingUser;
        $rootUser = DB::transaction(function () use (
            $superAdminEmail,
            $existingUser,
            $targetHashedPassword
        ) {
            // A. Ensure Master Platform Tenant (default-tenant)
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
            }

            // B. Provision or Update Root User
            if ($existingUser) {
                if ($targetHashedPassword !== null) {
                    $existingUser->password = $targetHashedPassword;
                }
                $existingUser->tenant_id = 'default-tenant';
                $existingUser->role = 'admin';
                $existingUser->disabled = false;
                $existingUser->permissions = json_encode(['all' => true]);
                $existingUser->save();
                $user = $existingUser;
            } else {
                $user = User::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => 'default-tenant',
                    'name' => 'Platform Super Admin',
                    'email' => $superAdminEmail,
                    'password' => $targetHashedPassword,
                    'role' => 'admin',
                    'disabled' => false,
                    'permissions' => json_encode(['all' => true]),
                ]);
            }

            // C. Invariant Assertion inside transaction (rolls back on failure)
            $user->refresh();
            if (!$user->isPlatformAdmin()) {
                throw new SecurityException("Verification Failed: Provisioned user '{$superAdminEmail}' does not satisfy isPlatformAdmin().");
            }

            return $user;
        });

        if ($isFresh) {
            $this->info("Created and verified platform master tenant and Super Admin: {$superAdminEmail}");
        } else {
            $this->info("Updated and verified platform Super Admin: {$superAdminEmail}");
        }

        return Command::SUCCESS;
    }
}
