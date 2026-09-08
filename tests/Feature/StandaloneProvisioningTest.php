<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Exceptions\SecurityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StandaloneProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => false]);
        config(['saas.super_admin_email' => 'admin@hysamventures.com']);
    }

    /**
     * Standalone seeding provisions exactly one admin user with hashed password.
     */
    public function test_standalone_seeding_provisions_initial_admin(): void
    {
        User::withoutGlobalScopes()->truncate();
        $this->assertEquals(0, User::withoutGlobalScopes()->count());

        putenv('SUPER_ADMIN_PASSWORD=HysamSecurePass#2026');
        $_ENV['SUPER_ADMIN_PASSWORD'] = 'HysamSecurePass#2026';

        Artisan::call('db:seed', ['--force' => true]);

        $admin = User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertFalse((bool) $admin->disabled);
        $this->assertTrue(Hash::check('HysamSecurePass#2026', $admin->password));
    }

    /**
     * Seeded administrator can log in via standard web login flow.
     */
    public function test_seeded_admin_can_login_via_web_login(): void
    {
        User::withoutGlobalScopes()->truncate();

        putenv('SUPER_ADMIN_PASSWORD=HysamSecurePass#2026');
        $_ENV['SUPER_ADMIN_PASSWORD'] = 'HysamSecurePass#2026';

        Artisan::call('db:seed', ['--force' => true]);

        $response = $this->post(route('login.post'), [
            'email' => 'admin@hysamventures.com',
            'password' => 'HysamSecurePass#2026',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertSame('admin@hysamventures.com', Auth::user()->email);
    }

    /**
     * Standalone seeding is idempotent: re-seeding preserves existing password.
     */
    public function test_standalone_reseeding_preserves_existing_password(): void
    {
        User::withoutGlobalScopes()->truncate();

        putenv('SUPER_ADMIN_PASSWORD=InitialPassword#2026');
        $_ENV['SUPER_ADMIN_PASSWORD'] = 'InitialPassword#2026';

        Artisan::call('db:seed', ['--force' => true]);

        $admin = User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->first();
        $this->assertNotNull($admin);

        // Simulate admin changing password to a new one
        $admin->password = Hash::make('ChangedPassword#2027');
        $admin->save();

        // Re-run seeder with the old initial password in env
        Artisan::call('db:seed', ['--force' => true]);

        $adminAfter = User::withoutGlobalScopes()->where('email', 'admin@hysamventures.com')->first();
        $this->assertTrue(Hash::check('ChangedPassword#2027', $adminAfter->password), 'Re-seeding must NEVER overwrite modified password');
        $this->assertFalse(Hash::check('InitialPassword#2026', $adminAfter->password));
    }

    /**
     * Production seeding rejects missing or weak credentials for initial provisioning.
     */
    public function test_production_seeding_rejects_weak_password(): void
    {
        $originalEnv = app()->environment();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            User::withoutGlobalScopes()->truncate();

            putenv('SUPER_ADMIN_PASSWORD=password');
            $_ENV['SUPER_ADMIN_PASSWORD'] = 'password';

            $this->expectException(SecurityException::class);
            $this->expectExceptionMessage('Security Violation');

            Artisan::call('db:seed', ['--force' => true]);
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnv);
            putenv('SUPER_ADMIN_PASSWORD');
            unset($_ENV['SUPER_ADMIN_PASSWORD']);
        }
    }

    /**
     * Verify all /install routes are completely removed and return 404.
     */
    public function test_install_routes_do_not_exist(): void
    {
        $installEndpoints = [
            '/install',
            '/install/requirements',
            '/install/database',
            '/install/admin',
            '/install/run',
            '/install/complete',
        ];

        foreach ($installEndpoints as $endpoint) {
            $response = $this->get($endpoint);
            $response->assertStatus(404);
        }
    }
}
