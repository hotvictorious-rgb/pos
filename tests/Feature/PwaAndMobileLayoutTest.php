<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PwaAndMobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['multitenancy.enabled' => true]);
    }

    public function test_pwa_manifest_file_exists_and_has_valid_json()
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);

        $content = file_get_contents($manifestPath);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertEquals('Victorious Market POS', $data['name']);
        $this->assertEquals('VM POS', $data['short_name']);
        $this->assertEquals('standalone', $data['display']);
        $this->assertEquals('#0c2340', $data['theme_color']);
        $this->assertEquals('#0b0f19', $data['background_color']);
        $this->assertNotEmpty($data['icons']);
    }

    public function test_service_worker_file_exists_and_contains_cache_rules()
    {
        $swPath = public_path('sw.js');
        $this->assertFileExists($swPath);

        $content = file_get_contents($swPath);
        $this->assertStringContainsString('vmpos-cache', $content);
        $this->assertStringContainsString("self.addEventListener('install'", $content);
        $this->assertStringContainsString("self.addEventListener('fetch'", $content);
    }

    public function test_pwa_icons_exist_with_proper_dimensions()
    {
        $icon192 = public_path('icons/icon-192x192.png');
        $icon512 = public_path('icons/icon-512x512.png');
        $appleIcon = public_path('icons/apple-touch-icon.png');

        $this->assertFileExists($icon192);
        $this->assertFileExists($icon512);
        $this->assertFileExists($appleIcon);

        $size192 = getimagesize($icon192);
        $this->assertEquals(192, $size192[0]);
        $this->assertEquals(192, $size192[1]);

        $size512 = getimagesize($icon512);
        $this->assertEquals(512, $size512[0]);
        $this->assertEquals(512, $size512[1]);
    }

    public function test_dashboard_renders_pwa_manifest_and_mobile_bottom_nav()
    {
        config(['saas.enabled' => true]);

        $tenant = Tenant::create([
            'id' => 'tenant-vic-' . Str::random(5),
            'name' => 'Victorious Store',
            'owner_email' => 'owner@victorious.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $wh = Warehouse::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Outlet',
            'code' => 'OUTLET-01',
            'is_active' => true,
        ]);

        $admin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'warehouse_id' => $wh->id,
            'name' => 'Store Owner',
            'username' => 'owner_vic',
            'email' => 'owner@victorious.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
            'permissions' => ['dashboard.view', 'pos.view', 'settings.manage'],
            'disabled' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('manifest.json', false);
        $response->assertSee('name="theme-color" content="#0c2340"', false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
        $response->assertSee('class="mobile-bottom-nav"', false);
        $response->assertSee('id="pwaInstallBanner"', false);
        $response->assertSee('sw.js', false);
    }
}
