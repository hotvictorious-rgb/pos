<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => false]);
    }

    /**
     * Unauthenticated guests are redirected to login.
     */
    public function test_guest_cannot_access_change_password_page(): void
    {
        $response = $this->get(route('account.password'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Authenticated users can view the change password page.
     */
    public function test_authenticated_user_can_view_change_password_page(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Manager',
            'email' => 'manager@hysam.com',
            'password' => Hash::make('InitialPass#2026'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('account.password'));
        $response->assertStatus(200);
        $response->assertSee('Change Password');
        $response->assertSee('manager@hysam.com');
    }

    /**
     * Fails when current password does not match.
     */
    public function test_change_password_fails_with_incorrect_current_password(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Manager',
            'email' => 'manager@hysam.com',
            'password' => Hash::make('CorrectOldPass#1'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'WrongPassword#123',
            'new_password' => 'BrandNewPass#2026',
            'new_password_confirmation' => 'BrandNewPass#2026',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('CorrectOldPass#1', $user->fresh()->password));
    }

    /**
     * Fails when new password violates PasswordPolicy rules.
     */
    public function test_change_password_enforces_password_policy(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Manager',
            'email' => 'manager@hysam.com',
            'password' => Hash::make('InitialPass#2026'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        // 1. Too short (< 8 chars)
        $resShort = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'InitialPass#2026',
            'new_password' => 'Sh0rt!',
            'new_password_confirmation' => 'Sh0rt!',
        ]);
        $resShort->assertSessionHasErrors(['new_password']);

        // 2. No uppercase
        $resNoUpper = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'InitialPass#2026',
            'new_password' => 'nouppercase123',
            'new_password_confirmation' => 'nouppercase123',
        ]);
        $resNoUpper->assertSessionHasErrors(['new_password']);

        // 3. No numeric digit
        $resNoDigit = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'InitialPass#2026',
            'new_password' => 'NoDigitHereAtAll',
            'new_password_confirmation' => 'NoDigitHereAtAll',
        ]);
        $resNoDigit->assertSessionHasErrors(['new_password']);
    }

    /**
     * Fails when confirmation does not match.
     */
    public function test_change_password_fails_when_confirmation_mismatches(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Manager',
            'email' => 'manager@hysam.com',
            'password' => Hash::make('InitialPass#2026'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'InitialPass#2026',
            'new_password' => 'ValidNewPass#1',
            'new_password_confirmation' => 'DifferentPass#2',
        ]);

        $response->assertSessionHasErrors(['new_password']);
    }

    /**
     * Fails when new password is same as current password.
     */
    public function test_change_password_fails_when_new_password_identical_to_current(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Manager',
            'email' => 'manager@hysam.com',
            'password' => Hash::make('InitialPass#2026'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'InitialPass#2026',
            'new_password' => 'InitialPass#2026',
            'new_password_confirmation' => 'InitialPass#2026',
        ]);

        $response->assertSessionHasErrors(['new_password']);
    }

    /**
     * Successfully changes password, updates hash, emits audit log, and works for subsequent login.
     */
    public function test_successful_password_change_lifecycle(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Admin Hysam',
            'email' => 'admin@hysamventures.com',
            'password' => Hash::make('OriginalProvisionedPass#2026'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'OriginalProvisionedPass#2026',
            'new_password' => 'BrandNewAuthoritativePass#2027',
            'new_password_confirmation' => 'BrandNewAuthoritativePass#2027',
        ]);

        $response->assertRedirect(route('account.password'));
        $response->assertSessionHas('success');

        // 1. Verify database hash updated
        $user->refresh();
        $this->assertTrue(Hash::check('BrandNewAuthoritativePass#2027', $user->password));
        $this->assertFalse(Hash::check('OriginalProvisionedPass#2026', $user->password));

        // 2. Verify audit trail emitted without plaintext password
        $activity = Activity::where('type', 'PASSWORD_CHANGED')->where('userId', $user->id)->first();
        $this->assertNotNull($activity);
        $this->assertSame($user->name, $activity->userName);
        $this->assertStringNotContainsString('OriginalProvisionedPass#2026', json_encode($activity));
        $this->assertStringNotContainsString('BrandNewAuthoritativePass#2027', json_encode($activity));

        // 3. Verify user remains authenticated
        $this->assertAuthenticatedAs($user);

        // 4. Test web login with OLD password fails
        Auth::logout();
        session()->flush();

        $oldLoginResponse = $this->post(route('login.post'), [
            'email' => 'admin@hysamventures.com',
            'password' => 'OriginalProvisionedPass#2026',
        ]);
        $oldLoginResponse->assertSessionHas('error');
        $this->assertGuest();

        // 5. Test web login with NEW password succeeds
        $newLoginResponse = $this->post(route('login.post'), [
            'email' => 'admin@hysamventures.com',
            'password' => 'BrandNewAuthoritativePass#2027',
        ]);
        $newLoginResponse->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertSame($user->id, Auth::id());
    }

    /**
     * JSON requests receive JSON responses without exposing sensitive credentials.
     */
    public function test_json_request_receives_json_response(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'API Admin',
            'email' => 'apiadmin@hysam.com',
            'password' => Hash::make('InitialPass#2026'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('account.password.update'), [
            'current_password' => 'InitialPass#2026',
            'new_password' => 'JsonNewSecurePass#2026',
            'new_password_confirmation' => 'JsonNewSecurePass#2026',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Password updated successfully.']);
        $this->assertStringNotContainsString('JsonNewSecurePass#2026', $response->getContent());
    }
}
