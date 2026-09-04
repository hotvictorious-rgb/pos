<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class ProductionSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $masterTenant;
    protected User $superAdmin;
    protected Tenant $tenantA;
    protected User $adminA;
    protected Warehouse $warehouseA;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);
        RateLimiter::clear(Str::transliterate('alpha@test.com|127.0.0.1'));
        RateLimiter::clear(Str::transliterate('login-ip|127.0.0.1'));

        $this->masterTenant = Tenant::firstOrCreate(
            ['id' => 'default-tenant'],
            [
                'name' => 'Default Platform Master Tenant',
                'owner_email' => 'admin@hysam.com',
                'owner_phone' => '0800000000',
                'plan' => 'enterprise',
                'status' => 'active',
            ]
        );

        $this->superAdmin = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => 'super-admin-prod',
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('supersecret'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $this->tenantA = Tenant::create([
            'id' => 'tenant-prod-a',
            'name' => 'Alpha Supermarket',
            'owner_email' => 'alpha@test.com',
            'owner_phone' => '0801111111',
            'plan' => 'pro',
            'status' => 'active',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Main Alpha Branch',
            'code' => 'WH-ALPHA-01',
            'is_active' => true,
        ]);

        $this->adminA = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Admin',
            'email' => 'alpha@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 1. CSRF PROTECTION TESTS
    // ─────────────────────────────────────────────────────────

    public function test_csrf_middleware_rejects_missing_token_with_token_mismatch_exception()
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests() {
                return false;
            }
        };

        $request = \Illuminate\Http\Request::create('/users', 'POST', [
            'name' => 'Attacker Injected User',
        ]);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $middleware->handle($request, function () {
            return response('OK');
        });
    }

    public function test_csrf_middleware_accepts_valid_matching_token()
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests() {
                return false;
            }
        };

        $session = $this->app['session']->driver();
        $session->start();
        $token = $session->token();

        $request = \Illuminate\Http\Request::create('/users', 'POST', [
            '_token' => $token,
            'name' => 'Legitimate User',
        ]);
        $request->setLaravelSession($session);

        $response = $middleware->handle($request, function () {
            return response('OK');
        });

        $this->assertEquals('OK', $response->getContent());
    }

    // ─────────────────────────────────────────────────────────
    // 2. AUTHENTICATION RATE LIMITING TESTS
    // ─────────────────────────────────────────────────────────

    public function test_rate_limiter_throttles_after_five_failed_login_attempts()
    {
        // 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('portal.tenant.login.post'), [
                'email' => 'alpha@test.com',
                'password' => 'wrongpassword',
            ]);
            $response->assertSessionHas('error');
            $this->assertStringNotContainsString('Too many login attempts', session('error'));
        }

        // 6th attempt: should be throttled
        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Too many login attempts', session('error'));
    }

    public function test_api_rate_limiter_returns_429_on_too_many_failed_attempts()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'alpha@test.com',
                'password' => 'badpass',
            ]);
        }

        // 6th attempt
        $throttled = $this->postJson('/api/login', [
            'email' => 'alpha@test.com',
            'password' => 'badpass',
        ]);

        $throttled->assertStatus(429);
        $throttled->assertJsonStructure(['error']);
        $this->assertStringContainsString('Too many login attempts', $throttled->json('error'));
    }

    public function test_successful_login_clears_rate_limiter()
    {
        // 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('portal.tenant.login.post'), [
                'email' => 'alpha@test.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Successful attempt
        $success = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);
        $success->assertRedirect('/');

        // Verify rate limiter was cleared (0 attempts recorded)
        $emailKey = Str::transliterate('alpha@test.com|127.0.0.1');
        $this->assertEquals(0, RateLimiter::attempts($emailKey));
    }

    // ─────────────────────────────────────────────────────────
    // 3. SECURITY HEADERS & COOKIE CONFIGURATION TESTS
    // ─────────────────────────────────────────────────────────

    public function test_security_headers_present_on_web_responses()
    {
        $response = $this->get('/tenant/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_session_cookie_configuration_is_secure()
    {
        $this->assertTrue(config('session.http_only'), 'Session cookie must have HttpOnly enabled.');
        $this->assertContains(config('session.same_site'), ['lax', 'strict'], 'Session cookie must be Lax or Strict.');
    }

    // ─────────────────────────────────────────────────────────
    // 4. FILE UPLOAD SECURITY TESTS
    // ─────────────────────────────────────────────────────────

    public function test_csv_import_rejects_non_csv_files()
    {
        $maliciousFile = UploadedFile::fake()->create('malicious.php', 10, 'application/x-php');

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->post('/products/import/csv', [
            'csv_file' => $maliciousFile,
        ]);

        $response->assertSessionHasErrors(['csv_file']);
    }

    public function test_tenant_admin_cannot_upload_backups()
    {
        $fakeJson = UploadedFile::fake()->createWithContent('backup.json', '{"data":{}}');

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->postJson('/api/backups/upload', [
            'backup_file' => $fakeJson,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Tenant users cannot access platform management.']);
    }

    public function test_backup_upload_rejects_executable_or_non_json_files()
    {
        $fakeExe = UploadedFile::fake()->create('exploit.exe', 50, 'application/x-msdownload');

        $response = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
            'portal' => 'super-admin',
        ])->postJson('/api/backups/upload', [
            'backup_file' => $fakeExe,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('must be a file of type: json, txt', $response->json('error'));
    }

    public function test_backup_upload_rejects_oversized_files()
    {
        // Create 15MB file (exceeds 10MB limit)
        $oversizedFile = UploadedFile::fake()->create('huge_backup.json', 15000, 'application/json');

        $response = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
            'portal' => 'super-admin',
        ])->postJson('/api/backups/upload', [
            'backup_file' => $oversizedFile,
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('greater than 10240 kilobytes', $response->json('error'));
    }

    public function test_backup_upload_rejects_malformed_json_content()
    {
        $corruptFile = UploadedFile::fake()->createWithContent('corrupt.json', '{ NOT VALID JSON !!!');

        $response = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
            'portal' => 'super-admin',
        ])->postJson('/api/backups/upload', [
            'backup_file' => $corruptFile,
        ]);

        $response->assertStatus(400);
        $this->assertEquals('Invalid backup file format.', $response->json('error'));
    }

    // ─────────────────────────────────────────────────────────
    // 5. ERROR DISCLOSURE HYGIENE (APP_DEBUG=false)
    // ─────────────────────────────────────────────────────────

    public function test_api_does_not_leak_stack_traces_or_database_errors_when_debug_false()
    {
        config(['app.debug' => false]);

        // Request an invalid backup ID as Super Admin that triggers a query
        $response = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
            'portal' => 'super-admin',
        ])->getJson('/api/backups/NON-EXISTENT-ID/download');

        $this->assertNotEquals(500, $response->status());
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('.php on line', $content);
        $this->assertStringNotContainsString('Stack trace:', $content);
    }
}
