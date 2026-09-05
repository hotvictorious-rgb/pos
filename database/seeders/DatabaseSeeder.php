<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with complete tenant and user details.
     */
    public function run(): void
    {
        $superAdminEmail = strtolower(trim(config('saas.super_admin_email', 'admin@hysamventures.com')));
        $superAdminPassword = env('SUPER_ADMIN_PASSWORD', 'changeme123');

        // 1. Seed Master Platform Tenant (default-tenant for Super Admin)
        $defaultTenant = Tenant::withoutGlobalScopes()->find('default-tenant')
            ?? Tenant::create([
                'id' => 'default-tenant',
                'name' => 'Platform Master HQ',
                'owner_email' => $superAdminEmail,
                'owner_phone' => '08000000000',
                'plan' => 'enterprise',
                'status' => 'active',
                'max_branches' => 999,
                'max_users' => 999,
            ]);

        // 2. Seed Business Tenant (Hysam Ventures HQ)
        $tenant = Tenant::withoutGlobalScopes()->find('tenant-1')
            ?? Tenant::create([
                'id' => 'tenant-1',
                'name' => 'Hysam Ventures HQ',
                'owner_email' => 'tenantadmin@hysam.com',
                'owner_phone' => '08011112222',
                'plan' => 'enterprise',
                'status' => 'active',
                'max_branches' => 10,
                'max_users' => 50,
            ]);

        // 3. Seed Platform Super Admin User (Super-Admin Console: /super-admin/login)
        $existingSuperAdmin = User::withoutGlobalScopes()->where('email', $superAdminEmail)->first();
        if ($existingSuperAdmin) {
            $existingSuperAdmin->update([
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'password' => Hash::make($superAdminPassword),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]);
        } else {
            User::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'email' => $superAdminEmail,
                'password' => Hash::make($superAdminPassword),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]);
        }

        // 4. Seed Business Tenant Admin User (Tenant Admin Portal: /tenant/login)
        $tenantAdminEmail = 'tenantadmin@hysam.com';
        $existingTenantAdmin = User::withoutGlobalScopes()->where('email', $tenantAdminEmail)->first();
        if ($existingTenantAdmin) {
            $existingTenantAdmin->update([
                'tenant_id' => $tenant->id,
                'name' => 'Business Owner / Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]);
        } else {
            User::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Business Owner / Admin',
                'email' => $tenantAdminEmail,
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]);
        }

        // 5. Seed Business Tenant Staff User (Employee Portal: /tenant-employee/login)
        $staffEmail = 'staff@hysam.com';
        $existingStaff = User::withoutGlobalScopes()->where('email', $staffEmail)->first();
        if ($existingStaff) {
            $existingStaff->update([
                'tenant_id' => $tenant->id,
                'name' => 'Sales Officer 1',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'disabled' => false,
                'permissions' => json_encode(['pos' => true, 'stockIn' => true]),
            ]);
        } else {
            User::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Sales Officer 1',
                'email' => $staffEmail,
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'disabled' => false,
                'permissions' => json_encode(['pos' => true, 'stockIn' => true]),
            ]);
        }

        // 6. Seed Default Settings
        $setting = Setting::withoutGlobalScopes()->find(1);
        if ($setting) {
            $setting->update([
                'tenant_id' => $tenant->id,
                'businessName' => 'VMARKET POS',
                'businessAddress' => '12 Commercial Avenue, Lagos, Nigeria',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'info@vmarketpos.com',
                'currency' => '₦',
                'categories' => json_encode(['Electronics', 'Groceries', 'Beverages', 'Hardware', 'Household']),
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 0,
                'fontFamily' => 'Inter',
            ]);
        } else {
            Setting::create([
                'id' => 1,
                'tenant_id' => $tenant->id,
                'businessName' => 'VMARKET POS',
                'businessAddress' => '12 Commercial Avenue, Lagos, Nigeria',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'info@vmarketpos.com',
                'currency' => '₦',
                'categories' => json_encode(['Electronics', 'Groceries', 'Beverages', 'Hardware', 'Household']),
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 0,
                'fontFamily' => 'Inter',
            ]);
        }

        // 7. Seed Warehouses / Branch Locations
        $shop1 = Warehouse::withoutGlobalScopes()->where('code', 'SHOP-01')->first()
            ?? Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'SHOP-01', 'name' => 'Main Store / Shop 1', 'address' => 'HQ Ground Floor', 'phone' => '08011111111', 'manager_name' => 'Shop Manager A']);

        $shop2 = Warehouse::withoutGlobalScopes()->where('code', 'SHOP-02')->first()
            ?? Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'SHOP-02', 'name' => 'Branch Store / Shop 2', 'address' => 'Ikeja Branch', 'phone' => '08022222222', 'manager_name' => 'Shop Manager B']);

        $warehouse = Warehouse::withoutGlobalScopes()->where('code', 'WH-01')->first()
            ?? Warehouse::create(['tenant_id' => $tenant->id, 'code' => 'WH-01', 'name' => 'Central Depot / Warehouse', 'address' => 'Industrial Estate', 'phone' => '08033333333', 'manager_name' => 'Warehouse Lead']);

        // 8. Seed Suppliers
        foreach ([
            ['name' => 'Dangote Sugar & Flour Refinery', 'contact_info' => 'dangote@supply.com', 'lead_time' => 3],
            ['name' => 'Golden Penny Mills Plc', 'contact_info' => 'golden@supply.com', 'lead_time' => 5],
            ['name' => 'Nestle Food Distributors', 'contact_info' => 'nestle@supply.com', 'lead_time' => 2],
        ] as $sup) {
            if (!Supplier::withoutGlobalScopes()->where('name', $sup['name'])->exists()) {
                Supplier::create(array_merge($sup, ['tenant_id' => $tenant->id]));
            }
        }

        // 9. Seed Demo Customers
        foreach ([
            ['name' => 'Alhaji Ibrahim & Sons', 'phone' => '08099887766', 'total_debt' => 45000, 'credit_limit' => 200000],
            ['name' => 'Mama Chinedu Provisions', 'phone' => '08055443322', 'total_debt' => 12000, 'credit_limit' => 50000],
            ['name' => 'Grace Supermarket', 'phone' => '08011223344', 'total_debt' => 0, 'credit_limit' => 500000],
        ] as $cust) {
            if (!Customer::withoutGlobalScopes()->where('name', $cust['name'])->exists()) {
                Customer::create(array_merge($cust, ['tenant_id' => $tenant->id]));
            }
        }

        // 10. Seed Sample Products & Physical Stock
        $sampleProducts = [
            ['code' => 'PROD-001', 'name' => 'Bag of Rice (50kg)', 'category' => 'Groceries', 'unitPrice' => 85000, 'stock1' => 40, 'stock2' => 15],
            ['code' => 'PROD-002', 'name' => 'Refined Vegetable Oil (25L)', 'category' => 'Groceries', 'unitPrice' => 52000, 'stock1' => 25, 'stock2' => 10],
            ['code' => 'PROD-003', 'name' => 'Sugar (50kg Bag)', 'category' => 'Groceries', 'unitPrice' => 78000, 'stock1' => 30, 'stock2' => 8],
            ['code' => 'PROD-004', 'name' => 'Carton of Indomie Noodles (40pk)', 'category' => 'Groceries', 'unitPrice' => 14500, 'stock1' => 100, 'stock2' => 45],
            ['code' => 'PROD-005', 'name' => 'Peak Milk Tin (Carton of 48)', 'category' => 'Beverages', 'unitPrice' => 38000, 'stock1' => 20, 'stock2' => 5],
            ['code' => 'PROD-006', 'name' => 'Milo Refill Pack (Carton)', 'category' => 'Beverages', 'unitPrice' => 32000, 'stock1' => 18, 'stock2' => 4],
        ];

        foreach ($sampleProducts as $pData) {
            $product = Product::withoutGlobalScopes()->where('code', $pData['code'])->first();
            if (!$product) {
                $product = Product::create([
                    'id' => (string) Str::uuid(),
                    'code' => $pData['code'],
                    'tenant_id' => $tenant->id,
                    'name' => $pData['name'],
                    'category' => $pData['category'],
                    'unitPrice' => $pData['unitPrice'],
                    'currentStock' => $pData['stock1'] + $pData['stock2'],
                    'minStockLevel' => 5,
                    'archived' => false,
                    'updatedAt' => now()->toIso8601String(),
                ]);
            }

            // Stock at Shop 1
            $st1 = StockLevel::withoutGlobalScopes()->where(['product_id' => $product->id, 'warehouse_id' => $shop1->id])->first();
            if ($st1) {
                $st1->update(['tenant_id' => $tenant->id, 'physical_stock' => $pData['stock1']]);
            } else {
                StockLevel::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'warehouse_id' => $shop1->id, 'physical_stock' => $pData['stock1'], 'allocated_stock' => 0, 'min_stock_alert' => 5]);
            }

            // Stock at Shop 2
            $st2 = StockLevel::withoutGlobalScopes()->where(['product_id' => $product->id, 'warehouse_id' => $shop2->id])->first();
            if ($st2) {
                $st2->update(['tenant_id' => $tenant->id, 'physical_stock' => $pData['stock2']]);
            } else {
                StockLevel::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'warehouse_id' => $shop2->id, 'physical_stock' => $pData['stock2'], 'allocated_stock' => 0, 'min_stock_alert' => 5]);
            }
        }
    }
}
