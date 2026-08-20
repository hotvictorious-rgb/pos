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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = strtolower(trim(env('ADMIN_EMAIL', 'admin@hysam.com')));
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        // 1. Seed Admin & Staff
        User::updateOrCreate(
            ['id' => 'admin-user-1'],
            [
                'name' => 'Auditor / Super Admin',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => json_encode(['all' => true]),
            ]
        );

        User::updateOrCreate(
            ['id' => 'staff-user-1'],
            [
                'name' => 'Sales Officer 1',
                'email' => 'staff@hysam.com',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'disabled' => false,
                'permissions' => json_encode(['pos' => true, 'stockIn' => true]),
            ]
        );

        // 2. Seed Default Settings
        Setting::updateOrCreate(
            ['id' => 1],
            [
                'businessName' => 'Hysam Ventures',
                'businessAddress' => '12 Commercial Avenue, Lagos, Nigeria',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'info@hysamventures.com',
                'currency' => '₦',
                'categories' => json_encode(['Electronics', 'Groceries', 'Beverages', 'Hardware', 'Household']),
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 0,
                'fontFamily' => 'Inter',
            ]
        );

        // 3. Seed Warehouses / Branch Locations
        $shop1 = Warehouse::firstOrCreate(
            ['code' => 'SHOP-01'],
            ['name' => 'Main Store / Shop 1', 'address' => 'HQ Ground Floor', 'phone' => '08011111111', 'manager_name' => 'Shop Manager A']
        );

        $shop2 = Warehouse::firstOrCreate(
            ['code' => 'SHOP-02'],
            ['name' => 'Branch Store / Shop 2', 'address' => 'Ikeja Branch', 'phone' => '08022222222', 'manager_name' => 'Shop Manager B']
        );

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-01'],
            ['name' => 'Central Depot / Warehouse', 'address' => 'Industrial Estate', 'phone' => '08033333333', 'manager_name' => 'Warehouse Lead']
        );

        // 4. Seed Suppliers
        Supplier::firstOrCreate(['name' => 'Dangote Sugar & Flour Refinery'], ['contact_info' => 'dangote@supply.com', 'lead_time' => 3]);
        Supplier::firstOrCreate(['name' => 'Golden Penny Mills Plc'], ['contact_info' => 'golden@supply.com', 'lead_time' => 5]);
        Supplier::firstOrCreate(['name' => 'Nestle Food Distributors'], ['contact_info' => 'nestle@supply.com', 'lead_time' => 2]);

        // 5. Seed Demo Customers
        Customer::firstOrCreate(['name' => 'Alhaji Ibrahim & Sons'], ['phone' => '08099887766', 'total_debt' => 45000, 'credit_limit' => 200000]);
        Customer::firstOrCreate(['name' => 'Mama Chinedu Provisions'], ['phone' => '08055443322', 'total_debt' => 12000, 'credit_limit' => 50000]);
        Customer::firstOrCreate(['name' => 'Grace Supermarket'], ['phone' => '08011223344', 'total_debt' => 0, 'credit_limit' => 500000]);

        // 6. Seed Sample Products & Physical Stock
        $sampleProducts = [
            ['code' => 'PROD-001', 'name' => 'Bag of Rice (50kg)', 'category' => 'Groceries', 'unitPrice' => 85000, 'stock1' => 40, 'stock2' => 15],
            ['code' => 'PROD-002', 'name' => 'Refined Vegetable Oil (25L)', 'category' => 'Groceries', 'unitPrice' => 52000, 'stock1' => 25, 'stock2' => 10],
            ['code' => 'PROD-003', 'name' => 'Sugar (50kg Bag)', 'category' => 'Groceries', 'unitPrice' => 78000, 'stock1' => 30, 'stock2' => 8],
            ['code' => 'PROD-004', 'name' => 'Carton of Indomie Noodles (40pk)', 'category' => 'Groceries', 'unitPrice' => 14500, 'stock1' => 100, 'stock2' => 45],
            ['code' => 'PROD-005', 'name' => 'Peak Milk Tin (Carton of 48)', 'category' => 'Beverages', 'unitPrice' => 38000, 'stock1' => 20, 'stock2' => 5],
            ['code' => 'PROD-006', 'name' => 'Milo Refill Pack (Carton)', 'category' => 'Beverages', 'unitPrice' => 32000, 'stock1' => 18, 'stock2' => 4],
        ];

        foreach ($sampleProducts as $pData) {
            $product = Product::firstOrCreate(
                ['code' => $pData['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $pData['name'],
                    'category' => $pData['category'],
                    'unitPrice' => $pData['unitPrice'],
                    'currentStock' => $pData['stock1'] + $pData['stock2'],
                    'minStockLevel' => 5,
                    'archived' => false,
                    'updatedAt' => now()->toIso8601String(),
                ]
            );

            // Stock at Shop 1
            StockLevel::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $shop1->id],
                ['physical_stock' => $pData['stock1'], 'allocated_stock' => 0, 'min_stock_alert' => 5]
            );

            // Stock at Shop 2
            StockLevel::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $shop2->id],
                ['physical_stock' => $pData['stock2'], 'allocated_stock' => 0, 'min_stock_alert' => 5]
            );
        }
    }
}
