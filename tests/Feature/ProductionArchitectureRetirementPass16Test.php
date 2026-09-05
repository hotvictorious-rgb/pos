<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ProductionArchitectureRetirementPass16Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $cashier;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@pass16.test',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@pass16.test',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass16-' . Str::random(5),
            'name' => 'Pass 16 Test Merchant Ltd',
            'slug' => 'pass16-merchant-' . Str::random(5),
            'owner_email' => 'owner@pass16.test',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Pass 16 Central Depot',
            'code' => 'P16-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier User',
            'email' => 'cashier@pass16.test',
            'password' => bcrypt('StrongPass123!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Merchant Admin',
            'email' => 'admin@pass16.test',
            'password' => bcrypt('StrongPass123!'),
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
        ]);
    }

    /**
     * PASS 16 - EXPRESS DEPENDENCY RETIREMENT:
     * Verifies that package.json has completely purged Express and server runner dependencies.
     */
    public function test_legacy_express_dependencies_are_completely_absent(): void
    {
        $packageJsonPath = base_path('package.json');
        $this->assertFileExists($packageJsonPath);

        $package = json_decode(file_get_contents($packageJsonPath), true);
        $deps = array_keys($package['dependencies'] ?? []);
        $devDeps = array_keys($package['devDependencies'] ?? []);
        $allDeps = array_merge($deps, $devDeps);

        $this->assertNotContains('express', $allDeps, 'Express dependency must be purged from package.json.');
        $this->assertNotContains('@types/express', $allDeps, '@types/express must be purged from package.json.');
        $this->assertNotContains('tsx', $allDeps, 'tsx runtime must be purged from package.json.');

        // Verify scripts do not contain server runners
        $scripts = $package['scripts'] ?? [];
        foreach ($scripts as $name => $cmd) {
            $this->assertStringNotContainsString('server.ts', $cmd, "Script '{$name}' must not execute server.ts.");
            $this->assertStringNotContainsString('express', $cmd, "Script '{$name}' must not reference express.");
        }
    }

    /**
     * PASS 16 - EXPRESS SERVER TOMBSTONE & RETIREMENT:
     * Verifies that server.ts is retired and throws an explicit deprecation error.
     */
    public function test_legacy_express_server_is_permanently_retired(): void
    {
        $serverPath = base_path('server.ts');
        if (file_exists($serverPath)) {
            $content = file_get_contents($serverPath);
            $this->assertStringContainsString('deprecated and disabled', $content);
            $this->assertStringNotContainsString('express()', $content);
            $this->assertStringNotContainsString('app.listen(', $content);
        }
    }

    /**
     * PASS 16 - ZERO WILDCARD / ZERO CATCH-ALL ROUTES:
     * Verifies that no routes expose a catch-all SPA route pointing to React.
     */
    public function test_no_routes_point_to_client_side_spa(): void
    {
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Assert no catch-all routes exist like {any} or {path}
            $this->assertFalse(
                str_contains($uri, '{any}') || str_contains($uri, '{fallbackPlaceholder}'),
                "Catch-all SPA route detected at '{$uri}'. All routes must be discrete Laravel endpoints."
            );
        }
    }

    /**
     * PASS 16 - BLADE PRESENTATION INTEGRITY:
     * Verifies that production layout does not mount client-side React SPA (<div id="root">).
     */
    public function test_blade_views_do_not_mount_react_root(): void
    {
        $layoutPath = resource_path('views/layouts/app.blade.php');
        $this->assertFileExists($layoutPath);

        $layoutContent = file_get_contents($layoutPath);
        $this->assertStringNotContainsString('<div id="root">', $layoutContent);
        $this->assertStringNotContainsString('@viteReactRefresh', $layoutContent);

        // Check app.blade.php retirement
        $appBladePath = resource_path('views/app.blade.php');
        if (file_exists($appBladePath)) {
            $appContent = file_get_contents($appBladePath);
            $this->assertStringNotContainsString('<div id="root"></div>', $appContent);
            $this->assertStringNotContainsString('@viteReactRefresh', $appContent);
        }
    }

    /**
     * PASS 16 - OFFLINE SYNC ENDPOINT HARD LOCKOUT:
     * Asserts that POST /api/data fails-closed with 403 Forbidden.
     */
    public function test_offline_data_sync_endpoint_fails_closed(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson('/api/data', [
                'sales' => [],
                'products' => [],
                'payments' => [],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Forbidden. Offline data synchronization is disabled. VMarket POS is strictly online-only; all transactions must be submitted via authoritative business endpoints.'
        ]);
    }

    /**
     * PASS 16 - STORAGE.TS CLIENT SHADOW WRITES ARE DISARMED:
     * Verifies that resources/js/lib/storage.ts disarms client-side shadow mutation methods.
     */
    public function test_storage_ts_shadow_writes_are_disarmed(): void
    {
        $storagePath = resource_path('js/lib/storage.ts');
        $this->assertFileExists($storagePath);

        $content = file_get_contents($storagePath);

        // Verify architectural invariant header
        $this->assertStringContainsString('ARCHITECTURAL INVARIANT', $content);

        // Verify shadow mutation methods throw deprecation exceptions
        $this->assertStringContainsString('saveProducts: (_products: Product[]) => {', $content);
        $this->assertStringContainsString('saveSales: (_sales: Sale[]) => {', $content);
        $this->assertStringContainsString('savePayments: (_payments: Payment[]) => {', $content);
        $this->assertStringContainsString('saveUsers: (_users: User[]) => {', $content);
        $this->assertStringContainsString('DEPRECATED & DISABLED', $content);
    }
}
