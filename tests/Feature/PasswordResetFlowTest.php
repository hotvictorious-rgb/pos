<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_loads_successfully(): void
    {
        $response = $this->get(route('password.request'));
        $response->assertStatus(200);
        $response->assertSee('Forgot Your Password?');
    }

    public function test_send_reset_link_fails_gracefully_for_unknown_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertSessionHas('error');
        $response->assertRedirect();
    }

    public function test_send_reset_link_generates_token_and_direct_link_when_mail_fails(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-' . Str::random(5),
            'name' => 'Test Store',
            'owner_email' => 'owner@teststore.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Store Manager',
            'email' => 'manager@teststore.com',
            'password' => Hash::make('OldPassword123#'),
            'role' => 'admin',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'manager@teststore.com',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'manager@teststore.com',
        ]);

        $response->assertSessionMissing('direct_reset_link');
        $response->assertSessionMissing('dev_reset_link');
        $response->assertRedirect();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-' . Str::random(5),
            'name' => 'Test Store',
            'owner_email' => 'owner2@teststore.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Store Manager',
            'email' => 'manager2@teststore.com',
            'password' => Hash::make('OldPassword123#'),
            'role' => 'admin',
        ]);

        $rawToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'manager2@teststore.com',
            'token' => hash('sha256', $rawToken),
            'created_at' => now(),
        ]);

        // Load reset page
        $pageResponse = $this->get(route('password.reset', ['token' => $rawToken, 'email' => 'manager2@teststore.com']));
        $pageResponse->assertStatus(200);

        // Submit new password
        $resetResponse = $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => 'manager2@teststore.com',
            'password' => 'NewSecurePassword123#',
            'password_confirmation' => 'NewSecurePassword123#',
        ]);

        $resetResponse->assertRedirect(route('portal.tenant.login'));
        $resetResponse->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123#', $user->password));
    }

    public function test_platform_super_admin_cannot_use_tenant_password_reset(): void
    {
        $platformAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('RootPassword123#'),
            'role' => 'super_admin',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'superadmin@hysam.com',
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionHas('error', 'Platform administrators cannot reset credentials via the tenant portal. Please contact server operations.');
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'superadmin@hysam.com',
        ]);
    }

    public function test_suspended_tenant_user_cannot_use_password_reset(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-' . Str::random(5),
            'name' => 'Suspended Store',
            'owner_email' => 'suspended@store.com',
            'status' => 'suspended',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Suspended Staff',
            'email' => 'staff@suspendedstore.com',
            'password' => Hash::make('Password123#'),
            'role' => 'cashier',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'staff@suspendedstore.com',
        ]);

        $response->assertSessionHas('error', 'Your business subscription has expired or been suspended.');
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'staff@suspendedstore.com',
        ]);
    }
}
