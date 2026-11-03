<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LocalTestSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------------------
        // 1. MASTER PLATFORM ROOT (SUPER ADMIN)
        // -------------------------------------------------------------------
        $superAdminEmail = strtolower(trim(env('SUPER_ADMIN_EMAIL', 'admin@hysamventures.com')));
        $superAdminPw = env('SUPER_ADMIN_PASSWORD', 'password123');

        $masterTenant = Tenant::withoutGlobalScopes()->find('default-tenant');
        if (!$masterTenant) {
            Tenant::create([
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

        $superAdmin = User::withoutGlobalScopes()->where('email', $superAdminEmail)->first();
        if ($superAdmin) {
            $superAdmin->update([
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'password' => Hash::make($superAdminPw),
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
                'password' => Hash::make($superAdminPw),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]);
        }

        // -------------------------------------------------------------------
        // 2. BUSINESS MERCHANT 1: VICTORIOUS MARKET SUPERSTORES
        // -------------------------------------------------------------------
        $victoriousTenant = Tenant::withoutGlobalScopes()->find('tenant-victorious');
        if (!$victoriousTenant) {
            Tenant::create([
                'id' => 'tenant-victorious',
                'name' => 'Victorious Market Superstores',
                'owner_email' => 'owner@victoriousmarket.ng',
                'owner_phone' => '08012345678',
                'plan' => 'enterprise',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(365),
                'max_branches' => 10,
                'max_users' => 50,
            ]);
        } else {
            $victoriousTenant->update([
                'name' => 'Victorious Market Superstores',
                'status' => 'active',
            ]);
        }

        // Business Settings for Victorious Market
        $settingData = [
            'tenant_id' => 'tenant-victorious',
            'businessName' => 'Victorious Market Superstores',
            'businessAddress' => '10 Abak Road, Uyo, Akwa Ibom State',
            'businessPhone' => '08012345678',
            'currency' => '₦',
            'lowStockThreshold' => 5,
            'transactionEditLimitDays' => 0,
            'fontFamily' => 'Plus Jakarta Sans',
            'reportFooter' => 'Thank you for your patronage! Goods sold in good condition cannot be returned after 3 days.',
            'owner_whatsapp_phone' => '08012345678',
            'whatsapp_report_enabled' => true,
            'whatsapp_report_time' => '21:00',
            'whatsapp_branch_breakdown' => true,
            'whatsapp_include_reversals' => true,
            'whatsapp_include_low_stock' => true,
            'whatsapp_include_inventory' => true,
            'whatsapp_include_transfers' => true,
            'whatsapp_include_deliveries' => true,
            'categories' => ['Groceries', 'Beverages', 'Commodities', 'Toiletries'],
        ];

        $setting = Setting::withoutGlobalScopes()->find(1) ?? Setting::withoutGlobalScopes()->where('tenant_id', 'tenant-victorious')->first();
        if ($setting) {
            $setting->update($settingData);
        } else {
            Setting::create(array_merge(['id' => 1], $settingData));
        }

        // Branches (Warehouses) for Victorious Market
        $wh1 = Warehouse::withoutGlobalScopes()->find(1);
        if ($wh1) {
            $wh1->update([
                'tenant_id' => 'tenant-victorious',
                'name' => 'Main Superstore (Branch 1)',
                'code' => 'VIC-MAIN',
                'address' => 'Plot 4, Abak Road, Uyo',
                'phone' => '08022221111',
                'manager_name' => 'Samuel Ade (Branch 1 Manager)',
                'manager_whatsapp_phone' => '08098765432',
                'whatsapp_report_enabled' => true,
                'is_active' => true,
            ]);
        } else {
            Warehouse::create([
                'id' => 1,
                'tenant_id' => 'tenant-victorious',
                'name' => 'Main Superstore (Branch 1)',
                'code' => 'VIC-MAIN',
                'address' => 'Plot 4, Abak Road, Uyo',
                'phone' => '08022221111',
                'manager_name' => 'Samuel Ade (Branch 1 Manager)',
                'manager_whatsapp_phone' => '08098765432',
                'whatsapp_report_enabled' => true,
                'is_active' => true,
            ]);
        }

        $wh2 = Warehouse::withoutGlobalScopes()->find(2);
        if ($wh2) {
            $wh2->update([
                'tenant_id' => 'tenant-victorious',
                'name' => 'Express Outlet (Branch 2)',
                'code' => 'VIC-EXP',
                'address' => '18 Ikot Ekpene Road, Uyo',
                'phone' => '08033332222',
                'manager_name' => 'Grace Obi (Branch 2 Manager)',
                'manager_whatsapp_phone' => '08011223344',
                'whatsapp_report_enabled' => true,
                'is_active' => true,
            ]);
        } else {
            Warehouse::create([
                'id' => 2,
                'tenant_id' => 'tenant-victorious',
                'name' => 'Express Outlet (Branch 2)',
                'code' => 'VIC-EXP',
                'address' => '18 Ikot Ekpene Road, Uyo',
                'phone' => '08033332222',
                'manager_name' => 'Grace Obi (Branch 2 Manager)',
                'manager_whatsapp_phone' => '08011223344',
                'whatsapp_report_enabled' => true,
                'is_active' => true,
            ]);
        }

        // Business Owner Users for Victorious Market
        $ownerEmails = [
            'owner@victoriousmarket.ng' => 'Elder Victorious (Managing Director)',
            'admin@victorious.ng' => 'Admin Owner',
        ];

        foreach ($ownerEmails as $email => $name) {
            $u = User::withoutGlobalScopes()->where('email', $email)->first();
            if ($u) {
                $u->update([
                    'tenant_id' => 'tenant-victorious',
                    'name' => $name,
                    'password' => Hash::make('password123'),
                    'role' => 'admin',
                    'warehouse_id' => null,
                    'permissions' => json_encode(['all' => true]),
                    'disabled' => false,
                ]);
            } else {
                User::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => 'tenant-victorious',
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'role' => 'admin',
                    'warehouse_id' => null,
                    'permissions' => json_encode(['all' => true]),
                    'disabled' => false,
                ]);
            }
        }

        // Cashier Staff User for Victorious Market Branch 1
        $cashier = User::withoutGlobalScopes()->where('email', 'cashier@victoriousmarket.ng')->first();
        if ($cashier) {
            $cashier->update([
                'tenant_id' => 'tenant-victorious',
                'name' => 'Blessing Cashier',
                'password' => Hash::make('password123'),
                'role' => 'cashier',
                'warehouse_id' => 1,
                'permissions' => json_encode(['pos' => true, 'stockIn' => false]),
                'disabled' => false,
            ]);
        } else {
            User::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => 'tenant-victorious',
                'name' => 'Blessing Cashier',
                'email' => 'cashier@victoriousmarket.ng',
                'password' => Hash::make('password123'),
                'role' => 'cashier',
                'warehouse_id' => 1,
                'permissions' => json_encode(['pos' => true, 'stockIn' => false]),
                'disabled' => false,
            ]);
        }

        // Sample Inventory Products for Victorious Market
        $prod = Product::withoutGlobalScopes()->where('id', 'PRD-MAMA-GOLD')->first();
        if ($prod) {
            $prod->update(['tenant_id' => 'tenant-victorious']);
        } else {
            $prod = Product::create([
                'id' => 'PRD-MAMA-GOLD',
                'tenant_id' => 'tenant-victorious',
                'name' => 'Mama Gold Rice 50kg',
                'code' => 'RICE-50KG',
                'category' => 'Commodities',
                'unitPrice' => 75000.00,
                'minStockLevel' => 5,
            ]);
        }

        $sl1 = StockLevel::withoutGlobalScopes()->where('product_id', $prod->id)->where('warehouse_id', 1)->first();
        if ($sl1) {
            $sl1->update(['tenant_id' => 'tenant-victorious', 'physical_stock' => 30, 'allocated_stock' => 0]);
        } else {
            StockLevel::create([
                'tenant_id' => 'tenant-victorious',
                'product_id' => $prod->id,
                'warehouse_id' => 1,
                'physical_stock' => 30,
                'allocated_stock' => 0,
            ]);
        }

        $sl2 = StockLevel::withoutGlobalScopes()->where('product_id', $prod->id)->where('warehouse_id', 2)->first();
        if ($sl2) {
            $sl2->update(['tenant_id' => 'tenant-victorious', 'physical_stock' => 15, 'allocated_stock' => 0]);
        } else {
            StockLevel::create([
                'tenant_id' => 'tenant-victorious',
                'product_id' => $prod->id,
                'warehouse_id' => 2,
                'physical_stock' => 15,
                'allocated_stock' => 0,
            ]);
        }

        // -------------------------------------------------------------------
        // 3. BUSINESS MERCHANT 2: HYSAM VENTURES HQ
        // -------------------------------------------------------------------
        $hysamTenant = Tenant::withoutGlobalScopes()->find('tenant-hysam');
        if (!$hysamTenant) {
            Tenant::create([
                'id' => 'tenant-hysam',
                'name' => 'Hysam Ventures HQ',
                'owner_email' => 'tenantadmin@hysam.com',
                'owner_phone' => '08022334455',
                'plan' => 'pro',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(180),
                'max_branches' => 5,
                'max_users' => 20,
            ]);
        }

        $hysamOwner = User::withoutGlobalScopes()->where('email', 'tenantadmin@hysam.com')->first();
        if ($hysamOwner) {
            $hysamOwner->update([
                'tenant_id' => 'tenant-hysam',
                'name' => 'Alhaji Hysam (Owner)',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'warehouse_id' => null,
                'permissions' => json_encode(['all' => true]),
                'disabled' => false,
            ]);
        } else {
            User::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => 'tenant-hysam',
                'name' => 'Alhaji Hysam (Owner)',
                'email' => 'tenantadmin@hysam.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'warehouse_id' => null,
                'permissions' => json_encode(['all' => true]),
                'disabled' => false,
            ]);
        }
    }
}
