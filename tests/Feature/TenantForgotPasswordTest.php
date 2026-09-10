<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);
    }

    /**
     * Guest can access forgot password page.
     */
    public function test_forgot_password_page_is_accessible(): void
    {
        $response = $this->get(route('password.request'));
        $response->assertStatus(200);
        $response->assertSee('Forgot Your Password?');
        $response->assertSee('Business Account Email');
    }

    /**
     * Tenant user receives reset token in password_reset_tokens table.
     */
    public function test_forgot_password_generates_token_for_valid_tenant_user(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-test-recovery',
            'name' => 'Recovery Test Store',
            'owner_email' => 'owner@recoverytest.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Owner',
            'email' => 'owner@recoverytest.com',
            'password' => Hash::make('OldPassword123#'),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
            'disabled' => false,
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'owner@recoverytest.com',
        ]);

        $response->assertSessionHas('status');

        // Verify token generated in database
        $record = DB::table('password_reset_tokens')->where('email', 'owner@recoverytest.com')->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->token);
    }

    /**
     * Submitting invalid email returns generic status (avoids enumeration).
     */
    public function test_forgot_password_with_nonexistent_email_returns_generic_status(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nobody@doesnotexist.com',
        ]);

        $response->assertSessionHas('status');
        $record = DB::table('password_reset_tokens')->where('email', 'nobody@doesnotexist.com')->first();
        $this->assertNull($record);
    }

    /**
     * Reset password page requires valid token.
     */
    public function test_reset_password_page_validates_token(): void
    {
        $rawToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'owner@validtoken.com',
            'token' => hash('sha256', $rawToken),
            'created_at' => now(),
        ]);

        // Valid token
        $response = $this->get(route('password.reset', ['token' => $rawToken, 'email' => 'owner@validtoken.com']));
        $response->assertStatus(200);
        $response->assertSee('Create New Password');

        // Invalid token redirects with error
        $badResponse = $this->get(route('password.reset', ['token' => 'completely-invalid-token', 'email' => 'owner@validtoken.com']));
        $badResponse->assertRedirect(route('password.request'));
        $badResponse->assertSessionHas('error');
    }

    /**
     * Successful reset updates user password, invalidates token, and logs audit record.
     */
    public function test_reset_password_updates_user_password_and_clears_token(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-reset-complete',
            'name' => 'Complete Reset Store',
            'owner_email' => 'owner@complete.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Business Owner',
            'email' => 'owner@complete.com',
            'password' => Hash::make('InitialPass#123'),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
            'disabled' => false,
        ]);

        $rawToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'owner@complete.com',
            'token' => hash('sha256', $rawToken),
            'created_at' => now(),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => 'owner@complete.com',
            'password' => 'NewSecurePass#2026',
            'password_confirmation' => 'NewSecurePass#2026',
        ]);

        $response->assertRedirect(route('portal.tenant.login'));
        $response->assertSessionHas('success');

        // Verify token deleted
        $this->assertNull(DB::table('password_reset_tokens')->where('email', 'owner@complete.com')->first());

        // Verify user password updated
        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass#2026', $user->password));

        // Verify activity audit log created
        $activity = Activity::withoutGlobalScopes()->where('type', 'PASSWORD_RESET')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('owner@complete.com', $activity->description);
    }
}
