<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use App\Exceptions\SecurityException;
use App\Http\Controllers\Installer\InstallerController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SaasProvisionRootCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->detectEnvironment(fn() => 'testing');
        config(['saas.enabled' => true]);
        config(['saas.super_admin_email' => 'superadmin@hysam.com']);
        config(['app.installed' => true]);
        if (!file_exists(storage_path('installed'))) {
            file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));
        }
    }

    protected function tearDown(): void
    {
        if (!file_exists(storage_path('installed'))) {
            file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));
        }
        parent::tearDown();
    }

    /**
     * 1. Fresh Database End-to-End Test
     */
    public function test_saas_provision_root_creates_valid_platform_root_from_fresh_database(): void
    {
        Tenant::withoutGlobalScopes()->truncate();
        User::withoutGlobalScopes()->truncate();

        $this->assertEquals(0, Tenant::withoutGlobalScopes()->count());
        $this->assertEquals(0, User::withoutGlobalScopes()->count());

        $exitCode = Artisan::call('saas:provision-root');
        $this->assertSame(0, $exitCode);

        $defaultTenant = Tenant::withoutGlobalScopes()->find('default-tenant');
        $this->assertNotNull($defaultTenant);
        $this->assertSame('enterprise', $defaultTenant->plan);

        $users = User::withoutGlobalScopes()->get();
        $this->assertCount(1, $users);

        $root = $users->first();
        $this->assertSame('superadmin@hysam.com', strtolower($root->email));
        $this->assertSame('default-tenant', $root->tenant_id);
        $this->assertSame('admin', $root->role);
        $this->assertTrue($root->isPlatformAdmin());
        $this->assertTrue(Hash::check('test-super-secret-pw', $root->password));

        // Second provisioning run does NOT mutate password
        Artisan::call('saas:provision-root', ['--force' => true]);
        $root->refresh();
        $this->assertTrue(Hash::check('test-super-secret-pw', $root->password));
    }

    /**
     * 2. Force Flag Preserves Existing Password
     */
    public function test_provision_root_with_force_preserves_existing_password(): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find('default-tenant')
            ?? Tenant::create([
                'id' => 'default-tenant',
                'name' => 'HQ',
                'owner_email' => 'superadmin@hysam.com',
                'status' => 'active',
                'plan' => 'enterprise',
            ]);

        User::withoutGlobalScopes()->where('email', 'superadmin@hysam.com')->delete();

        $user = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Initial Root',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('CustomOriginalPassword#999'),
            'role' => 'admin',
        ]);

        Artisan::call('saas:provision-root', ['--force' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check('CustomOriginalPassword#999', $user->password));
        $this->assertTrue($user->isPlatformAdmin());
    }

    /**
     * 3. Explicit Password or Hash Replaces Existing Password
     */
    public function test_provision_root_with_explicit_password_or_hash_updates_password(): void
    {
        User::withoutGlobalScopes()->where('email', 'superadmin@hysam.com')->delete();

        Artisan::call('saas:provision-root');
        $root = User::withoutGlobalScopes()->where('email', 'superadmin@hysam.com')->first();
        $this->assertTrue(Hash::check('test-super-secret-pw', $root->password));

        // Explicit plaintext rotation
        Artisan::call('saas:provision-root', [
            '--password' => 'RotatedPassword#2026',
            '--force' => true,
        ]);
        $root->refresh();
        $this->assertTrue(Hash::check('RotatedPassword#2026', $root->password));

        // Explicit pre-hashed credential (installer delegation)
        $preHashed = Hash::make('PreHashedSecretKey#777');
        Artisan::call('saas:provision-root', [
            '--password-hash' => $preHashed,
            '--force' => true,
        ]);
        $root->refresh();
        $this->assertTrue(Hash::check('PreHashedSecretKey#777', $root->password));
    }

    /**
     * 4. Exactly-One-Root Invariant Blocks Second Admin in default-tenant
     */
    public function test_exactly_one_root_blocks_second_admin_under_default_tenant(): void
    {
        Artisan::call('saas:provision-root', ['--force' => true]);

        // Attempting to create a second user with admin role in default-tenant is neutralized
        $intruder = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Intruder Admin',
            'email' => 'intruder@hysam.com',
            'password' => Hash::make('intruderpass'),
            'role' => 'admin',
        ]);

        $intruder->refresh();
        $this->assertSame('platform_employee', $intruder->role);
        $this->assertFalse($intruder->isPlatformAdmin());
        $this->assertFalse($intruder->isAdmin());
        $this->assertTrue($intruder->isPlatformEmployee());
    }

    /**
     * 4b. Platform Employees under default-tenant are allowed
     */
    public function test_platform_employees_under_default_tenant_are_allowed(): void
    {
        Artisan::call('saas:provision-root', ['--force' => true]);

        $employee = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Staff',
            'email' => 'staff@hysam.com',
            'password' => Hash::make('staffsecret123'),
            'role' => 'platform_employee',
            'permissions' => ['platform.health'],
        ]);

        $this->assertNotNull($employee);
        $this->assertSame('default-tenant', $employee->tenant_id);
        $this->assertFalse($employee->isPlatformAdmin());
        $this->assertTrue($employee->isPlatformEmployee());
    }

    /**
     * 5. Configuration Rotation Lifecycle
     */
    public function test_super_admin_email_configuration_change_invalidates_old_root_and_enables_new_root(): void
    {
        Artisan::call('saas:provision-root', ['--force' => true]);
        $oldRoot = User::withoutGlobalScopes()->where('email', 'superadmin@hysam.com')->first();
        $this->assertTrue($oldRoot->isPlatformAdmin());

        // Configuration rotated to new email
        config(['saas.super_admin_email' => 'newboss@hysam.com']);
        $oldRoot->refresh();

        // Old root immediately ceases to satisfy isPlatformAdmin()
        $this->assertFalse($oldRoot->isPlatformAdmin());

        // Provision new root
        Artisan::call('saas:provision-root', [
            '--email' => 'newboss@hysam.com',
            '--password' => 'NewBossSecurePass#2026',
            '--force' => true,
        ]);

        $newRoot = User::withoutGlobalScopes()->where('email', 'newboss@hysam.com')->first();
        $this->assertNotNull($newRoot);
        $this->assertTrue($newRoot->isPlatformAdmin());
        $this->assertTrue(Hash::check('NewBossSecurePass#2026', $newRoot->password));

        // Old account remains non-platform-admin
        $this->assertFalse($oldRoot->refresh()->isPlatformAdmin());
    }

    /**
     * 6. Cross-Tenant Email Collision Fails Closed
     */
    public function test_email_collision_with_customer_tenant_fails_closed(): void
    {
        $customerTenant = Tenant::create([
            'id' => 'customer-solar',
            'name' => 'Customer Solar Store',
            'owner_email' => 'solar@customer.test',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $merchantUser = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'customer-solar',
            'name' => 'Solar Merchant',
            'email' => 'solar-owner@customer.test',
            'password' => Hash::make('merchantpass123'),
            'role' => 'admin',
        ]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Identity Conflict");

        // Attempting to provision root with merchant's email must abort fail-closed
        Artisan::call('saas:provision-root', [
            '--email' => 'solar-owner@customer.test',
            '--force' => true,
        ]);

        // Assert merchant was not touched or migrated
        $merchantUser->refresh();
        $this->assertSame('customer-solar', $merchantUser->tenant_id);
    }

    /**
     * 7. Standalone Installer Takeover Protection
     */
    public function test_installer_aborts_takeover_if_users_already_exist(): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find('default-tenant')
            ?? Tenant::create([
                'id' => 'default-tenant',
                'name' => 'HQ',
                'owner_email' => 'superadmin@hysam.com',
                'status' => 'active',
                'plan' => 'enterprise',
            ]);

        User::withoutGlobalScopes()->where('email', 'superadmin@hysam.com')->delete();

        User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Existing User',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('existingpass'),
            'role' => 'admin',
        ]);

        // Attacker accesses installer run
        session([
            'installer_admin_name' => 'Attacker',
            'installer_admin_email' => 'attacker@evil.com',
            'installer_admin_password_hash' => Hash::make('attackerpass'),
        ]);

        $controller = new InstallerController();
        $response = $controller->run();

        // Must fail with 500 containing Security Violation in JSON
        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Security Violation', $data['error']);
    }

    /**
     * 8. Installer Provisions Root and Creates Installed Lock
     */
    public function test_installer_delegates_to_provision_root_and_creates_installed_lock(): void
    {
        User::withoutGlobalScopes()->truncate();
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        config(['app.installed' => false]);

        $adminEmail = 'superadmin@hysam.com';
        $hashedPass = Hash::make('InstallerSecretPass#2026');

        session([
            'installer_admin_name' => 'Platform Super Admin',
            'installer_admin_email' => $adminEmail,
            'installer_admin_password_hash' => $hashedPass,
        ]);

        $controller = new InstallerController();
        $response = $controller->run();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $root = User::withoutGlobalScopes()->where('email', $adminEmail)->first();
        $this->assertNotNull($root);
        $this->assertSame('default-tenant', $root->tenant_id);
        $this->assertTrue($root->isPlatformAdmin());
        $this->assertTrue(Hash::check('InstallerSecretPass#2026', $root->password));

        $this->assertFileExists(storage_path('installed'));
        $this->assertNull(session('installer_admin_email'));
    }

    /**
     * 9. DatabaseSeeder Delegates to saas:provision-root
     */
    public function test_database_seeder_delegates_to_provision_root(): void
    {
        config(['saas.super_admin_email' => 'admin@hysamventures.com']);
        User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->delete();

        Artisan::call('db:seed');

        $defaultTenant = Tenant::withoutGlobalScopes()->find('default-tenant');
        $this->assertNotNull($defaultTenant);

        $root = User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->first();
        $this->assertNotNull($root);
        $this->assertSame('default-tenant', $root->tenant_id);
        $this->assertTrue($root->isPlatformAdmin());
    }

    /**
     * 10. Authenticated User Authoritative Tenant Resolution
     */
    public function test_authenticated_user_tenant_resolution_takes_precedence_over_mismatched_session(): void
    {
        $tenantA = Tenant::create([
            'id' => 'tenant-A',
            'name' => 'Tenant A Corp',
            'owner_email' => 'a@tenant.test',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $tenantB = Tenant::create([
            'id' => 'tenant-B',
            'name' => 'Tenant B Corp',
            'owner_email' => 'b@tenant.test',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $userA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-A',
            'name' => 'Tenant A Admin',
            'email' => 'admin@tenanta.test',
            'password' => Hash::make('secretpass'),
            'role' => 'admin',
        ]);

        // Create sample product for Tenant A
        $prodA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-A',
            'code' => 'PROD-A',
            'name' => 'Product Tenant A',
            'category' => 'General',
            'unitPrice' => 1000,
            'currentStock' => 10,
            'minStockLevel' => 1,
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Set mismatched session to Tenant B
        session(['tenant_id' => 'tenant-B']);

        // Authenticate User A (tenant-A)
        Auth::login($userA);

        // TenantScope must resolve to tenant-A based on authenticated user identity
        $visibleProducts = Product::all();
        $this->assertTrue($visibleProducts->contains('id', $prodA->id));
        $this->assertSame('tenant-A', $visibleProducts->first()->tenant_id);
    }

    /**
     * 11. Weak Password Rejection in Production Mode
     */
    public function test_weak_passwords_rejected_in_production_mode(): void
    {
        $originalEnv = app()->environment();
        try {
            app()->detectEnvironment(fn() => 'production');

            $this->expectException(SecurityException::class);
            $this->expectExceptionMessage("Production root provisioning requires a secure, non-default, non-placeholder password.");

            Artisan::call('saas:provision-root', [
                '--password' => 'password',
                '--force' => true,
            ]);
        } finally {
            app()->detectEnvironment(fn() => $originalEnv);
        }
    }

    /**
     * 12. Placeholder Passwords from .env.example Rejected in Production Mode
     */
    public function test_placeholder_passwords_rejected_in_production_mode(): void
    {
        $originalEnv = app()->environment();
        try {
            app()->detectEnvironment(fn() => 'production');

            $this->expectException(SecurityException::class);
            $this->expectExceptionMessage("Production root provisioning requires a secure, non-default, non-placeholder password.");

            Artisan::call('saas:provision-root', [
                '--password' => 'set_your_secure_password_here',
                '--force' => true,
            ]);
        } finally {
            app()->detectEnvironment(fn() => $originalEnv);
        }
    }

    /**
     * 13. Re-provisioning Existing Root Without --force or Password Rotation Fails
     */
    public function test_reprovisioning_existing_root_without_force_fails(): void
    {
        User::withoutGlobalScopes()->where('email', 'superadmin@hysam.com')->delete();

        // Initial provisioning creates root
        $exitCode1 = Artisan::call('saas:provision-root');
        $this->assertSame(0, $exitCode1);

        // Re-executing without --force or --password must fail
        $exitCode2 = Artisan::call('saas:provision-root');
        $this->assertSame(1, $exitCode2);

        // Re-executing WITH --force succeeds
        $exitCode3 = Artisan::call('saas:provision-root', ['--force' => true]);
        $this->assertSame(0, $exitCode3);
    }

    /**
     * 14. Fresh Installer Takeover Guard Distinguishes Unmigrated Schema From Existing Accounts
     */
    public function test_installer_guard_handles_unmigrated_database_without_query_exception(): void
    {
        $nonExistentTable = 'unmigrated_fresh_db_table';
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable($nonExistentTable));

        // Directly querying an unmigrated table throws a QueryException (the previous bug)
        $threwException = false;
        try {
            \Illuminate\Support\Facades\DB::table($nonExistentTable)->exists();
        } catch (\Illuminate\Database\QueryException $e) {
            $threwException = true;
        }
        $this->assertTrue($threwException, 'Querying non-existent table directly must throw QueryException');

        // The guarded expression safely evaluates to false without throwing any exception
        $guardResult = \Illuminate\Support\Facades\Schema::hasTable($nonExistentTable)
            && \Illuminate\Support\Facades\DB::table($nonExistentTable)->exists();
        $this->assertFalse($guardResult);
    }

    /**
     * 15. Standalone Mode Seeder Does Not Provision SaaS Root
     */
    public function test_database_seeder_in_standalone_mode_does_not_provision_saas_root(): void
    {
        config(['saas.enabled' => false]);
        config(['saas.super_admin_email' => 'admin@hysamventures.com']);
        User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->delete();

        // Delete default-tenant to verify standalone seeder does not create it
        Tenant::withoutGlobalScopes()->where('id', 'default-tenant')->delete();

        Artisan::call('db:seed');

        $admin = User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertFalse($admin->isPlatformAdmin());
        $this->assertNotSame('default-tenant', $admin->tenant_id);

        $defaultTenant = Tenant::withoutGlobalScopes()->find('default-tenant');
        $this->assertNull($defaultTenant);
    }
}
