<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\SalesReturn;
use App\Models\InventoryLog;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['id' => 'ADMIN-EXPORT-1'],
            [
                'name' => 'Export Tester',
                'email' => 'exporttest@hysam.com',
                'password' => Hash::make('secret123'),
                'role' => 'admin',
                'disabled' => false,
            ]
        );
        $this->actingAs($this->user);
        session(['user_id' => $this->user->id, 'user_role' => 'admin']);

        $this->warehouseA = Warehouse::firstOrCreate(
            ['code' => 'EXP-WH-A'],
            ['name' => 'Main Depot A', 'address' => 'HQ', 'is_active' => true]
        );
        $this->warehouseB = Warehouse::firstOrCreate(
            ['code' => 'EXP-WH-B'],
            ['name' => 'Branch Shop B', 'address' => 'Branch', 'is_active' => true]
        );

        $this->product = Product::firstOrCreate(
            ['code' => 'EXP-PROD-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Test Cooking Oil 25L',
                'category' => 'Edible Oil',
                'unitPrice' => 45000,
                'currentStock' => 30,
                'archived' => false,
                'updatedAt' => now()->toIso8601String(),
            ]
        );
    }

    public function test_export_csv_for_filtered_sales()
    {
        Sale::create([
            'id' => 'INV-EXP-001',
            'customerName' => 'David Adeleke',
            'totalAmount' => 90000,
            'paidAmount' => 90000,
            'cashAmount' => 90000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Sale::create([
            'id' => 'INV-EXP-002',
            'customerName' => 'Wizkid Balogun',
            'totalAmount' => 45000,
            'paidAmount' => 20000,
            'cashAmount' => 20000,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'UNSUPPLIED',
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Filter by PAID
        $response = $this->get(route('transactions.export.csv', ['tab' => 'sales', 'payment_status' => 'PAID']));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('INV-EXP-001', $content);
        $this->assertStringContainsString('David Adeleke', $content);
        $this->assertStringNotContainsString('INV-EXP-002', $content); // Part-paid should be filtered out
    }

    public function test_export_json_for_sales()
    {
        Sale::create([
            'id' => 'INV-JSON-001',
            'customerName' => 'Burna Boy',
            'totalAmount' => 135000,
            'paidAmount' => 135000,
            'cashAmount' => 135000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $response = $this->get(route('transactions.export.json', ['tab' => 'sales']));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'metadata' => ['tab', 'generated_at', 'total_records'],
            'data' => [
                '*' => ['id', 'customerName', 'totalAmount']
            ]
        ]);
        $this->assertStringContainsString('INV-JSON-001', $response->getContent());
    }

    public function test_export_csv_for_all_remaining_tabs()
    {
        // 1. Stock In
        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'productId' => $this->product->id,
            'type' => 'STOCK_IN',
            'quantity' => 50,
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'description' => 'Supplier Delivery Inflow',
            'timestamp' => now()->toIso8601String(),
        ]);

        // 2. Stock Out
        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'productId' => $this->product->id,
            'type' => 'DISPATCH_FULFILLED',
            'quantity' => -10,
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'description' => 'Customer Handover Dispatch',
            'timestamp' => now()->toIso8601String(),
        ]);

        // 3. In-Transit Transfer
        $transfer = Transfer::create([
            'transfer_no' => 'TRF-EXP-999',
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseB->id,
            'carrier_name' => 'Driver Musa',
            'status' => 'DISPATCHED',
            'dispatched_by' => $this->user->name,
            'dispatched_at' => now(),
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'dispatched_qty' => 15,
            'received_qty' => 0,
            'discrepancy_qty' => 0,
        ]);

        // 4. Returns & Refunds
        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'code' => 'RET-EXP-101',
            'saleId' => 'INV-EXP-001',
            'customerName' => 'David Adeleke',
            'productId' => $this->product->id,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'quantity' => 2,
            'refundAmount' => 90000,
            'reason' => 'Defective seal',
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // 5. Debts
        $customer = Customer::create([
            'name' => 'Tiwa Savage',
            'phone' => '08099887766',
            'total_debt' => 50000,
        ]);

        CustomerLedger::create([
            'customer_id' => $customer->id,
            'type' => 'PAYMENT',
            'amount' => 25000,
            'balance_after' => 25000,
            'payment_method' => 'TRANSFER',
            'recorded_by' => $this->user->name,
            'created_at' => now(),
        ]);

        $tabs = ['stock_in', 'stock_out', 'in_transit', 'transfers_in', 'returns', 'refunds', 'debts'];

        foreach ($tabs as $tab) {
            $resp = $this->get(route('transactions.export.csv', ['tab' => $tab]));
            $resp->assertStatus(200);
            $this->assertNotEmpty($resp->streamedContent(), "Tab {$tab} CSV content was empty");

            $jsonResp = $this->get(route('transactions.export.json', ['tab' => $tab]));
            $jsonResp->assertStatus(200);
        }
    }
}
