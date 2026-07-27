<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Models\InventoryLog;
use App\Models\Activity;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DataController extends Controller
{
    private function checkAuth()
    {
        $userId = session('user_id');
        if (!$userId) {
            return null;
        }
        $user = User::find($userId);
        return ($user && !$user->disabled) ? $user : null;
    }

    private function getSettings()
    {
        $settings = Setting::find(1);
        if (!$settings) {
            $settings = Setting::create([
                'id' => 1,
                'businessName' => 'HYSAM VENTURES',
                'businessAddress' => '123 Main Street, Lagos, Nigeria',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'info@hysam.com',
                'currency' => '₦',
                'categories' => ['Power', 'Solar', 'Battery', 'Inverter', 'Accessories', 'General'],
                'reportFooter' => 'Thank you for your business!',
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 7,
                'fontFamily' => 'Inter'
            ]);
        }
        return $settings;
    }

    public function get()
    {
        if (!$this->checkAuth()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $users = User::all();
        $products = Product::all();
        
        $sales = Sale::with('items')->get()->map(function ($sale) {
            $saleArray = $sale->toArray();
            $saleArray['items'] = $sale->items->map(function ($item) {
                return [
                    'productId' => $item->productId,
                    'productName' => $item->productName,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unitPrice,
                    'totalPrice' => $item->totalPrice,
                    'code' => $item->code,
                    'productCode' => $item->productCode
                ];
            })->toArray();
            return $saleArray;
        });

        $payments = Payment::all();
        $logs = InventoryLog::all();
        $returns = SalesReturn::all();
        $activities = Activity::all();
        $settings = $this->getSettings();
        $customRoles = \App\Models\CustomRole::all();

        return response()->json([
            'users' => $users,
            'products' => $products,
            'sales' => $sales,
            'payments' => $payments,
            'logs' => $logs,
            'returns' => $returns,
            'activities' => $activities,
            'settings' => $settings,
            'custom_roles' => $customRoles
        ]);
    }

    public function post(Request $request)
    {
        $currentUser = $this->checkAuth();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $payload = $request->json()->all();

        // Server-side RBAC (Role-Based Access Control) Protection
        if ($currentUser->role !== 'admin') {
            // 1. Non-admins cannot modify users, settings, or custom roles
            if (isset($payload['users']) || isset($payload['settings']) || isset($payload['custom_roles'])) {
                return response()->json(['error' => 'Forbidden. Only administrators can modify users, settings, or custom roles.'], 403);
            }

            // 2. Non-admins cannot delete products
            if (isset($payload['products'])) {
                $ids = collect($payload['products'])->pluck('id')->all();
                if (Product::whereNotIn('id', $ids)->exists()) {
                    return response()->json(['error' => 'Forbidden. Only administrators can delete products.'], 403);
                }
            }

            // 3. Non-admins cannot delete sales
            if (isset($payload['sales'])) {
                $saleIds = collect($payload['sales'])->pluck('id')->all();
                if (Sale::whereNotIn('id', $saleIds)->exists()) {
                    return response()->json(['error' => 'Forbidden. Only administrators can delete sales.'], 403);
                }
            }
        }

        DB::transaction(function () use ($payload) {
            // 1. Sync Users (merge)
            if (isset($payload['users']) && is_array($payload['users'])) {
                foreach ($payload['users'] as $u) {
                    $userData = [
                        'name' => $u['name'] ?? '',
                        'email' => $u['email'] ?? '',
                        'role' => $u['role'] ?? 'staff',
                        'disabled' => $u['disabled'] ?? false,
                        'permissions' => $u['permissions'] ?? null,
                    ];
                    
                    if (isset($u['password']) && !empty($u['password'])) {
                        $userData['password'] = Hash::make($u['password']);
                    }

                    User::updateOrCreate(
                        ['id' => $u['id']],
                        $userData
                    );
                }
            }

            // 2. Sync Products
            if (isset($payload['products']) && is_array($payload['products'])) {
                $ids = collect($payload['products'])->pluck('id')->all();
                Product::whereNotIn('id', $ids)->delete();
                foreach ($payload['products'] as $p) {
                    Product::updateOrCreate(
                        ['id' => $p['id']],
                        [
                            'code' => $p['code'] ?? '',
                            'name' => $p['name'] ?? '',
                            'size' => $p['size'] ?? null,
                            'brand' => $p['brand'] ?? null,
                            'description' => $p['description'] ?? null,
                            'category' => $p['category'] ?? 'General',
                            'unitPrice' => $p['unitPrice'] ?? 0,
                            'currentStock' => $p['currentStock'] ?? 0,
                            'minStockLevel' => $p['minStockLevel'] ?? 2,
                            'archived' => $p['archived'] ?? false,
                            'userId' => $p['userId'] ?? null,
                            'updatedAt' => $p['updatedAt'] ?? now()->toIso8601String(),
                        ]
                    );
                }
            }

            // 3. Sync Sales and Sale Items
            if (isset($payload['sales']) && is_array($payload['sales'])) {
                $saleIds = collect($payload['sales'])->pluck('id')->all();
                
                SaleItem::whereNotIn('saleId', $saleIds)->delete();
                Sale::whereNotIn('id', $saleIds)->delete();

                foreach ($payload['sales'] as $s) {
                    Sale::updateOrCreate(
                        ['id' => $s['id']],
                        [
                            'customerName' => $s['customerName'] ?? null,
                            'totalAmount' => $s['totalAmount'] ?? 0,
                            'paidAmount' => $s['paidAmount'] ?? 0,
                            'cashAmount' => $s['cashAmount'] ?? 0,
                            'posAmount' => $s['posAmount'] ?? 0,
                            'note' => $s['note'] ?? null,
                            'status' => $s['status'] ?? 'completed',
                            'deliveryStatus' => $s['deliveryStatus'] ?? 'none',
                            'deliveredAt' => $s['deliveredAt'] ?? null,
                            'deliveredBy' => $s['deliveredBy'] ?? null,
                            'returnReason' => $s['returnReason'] ?? null,
                            'userId' => $s['userId'] ?? '',
                            'userName' => $s['userName'] ?? null,
                            'createdAt' => $s['createdAt'] ?? now()->toIso8601String(),
                        ]
                    );

                    if (isset($s['items']) && is_array($s['items'])) {
                        SaleItem::where('saleId', $s['id'])->delete();
                        foreach ($s['items'] as $item) {
                            SaleItem::create([
                                'saleId' => $s['id'],
                                'productId' => $item['productId'] ?? '',
                                'productName' => $item['productName'] ?? '',
                                'quantity' => $item['quantity'] ?? 0,
                                'unitPrice' => $item['unitPrice'] ?? 0,
                                'totalPrice' => $item['totalPrice'] ?? 0,
                                'code' => $item['code'] ?? null,
                                'productCode' => $item['productCode'] ?? null,
                            ]);
                        }
                    }
                }
            }

            // 4. Sync Payments
            if (isset($payload['payments']) && is_array($payload['payments'])) {
                $ids = collect($payload['payments'])->pluck('id')->all();
                Payment::whereNotIn('id', $ids)->delete();
                foreach ($payload['payments'] as $pay) {
                    Payment::updateOrCreate(
                        ['id' => $pay['id']],
                        [
                            'saleId' => $pay['saleId'] ?? '',
                            'amount' => $pay['amount'] ?? 0,
                            'method' => $pay['method'] ?? 'cash',
                            'timestamp' => $pay['timestamp'] ?? now()->toIso8601String(),
                            'recordedBy' => $pay['recordedBy'] ?? '',
                            'createdAt' => $pay['createdAt'] ?? null,
                        ]
                    );
                }
            }

            // 5. Sync Returns
            if (isset($payload['returns']) && is_array($payload['returns'])) {
                $ids = collect($payload['returns'])->pluck('id')->all();
                SalesReturn::whereNotIn('id', $ids)->delete();
                foreach ($payload['returns'] as $ret) {
                    SalesReturn::updateOrCreate(
                        ['id' => $ret['id']],
                        [
                            'saleId' => $ret['saleId'] ?? '',
                            'customerName' => $ret['customerName'] ?? null,
                            'code' => $ret['code'] ?? '',
                            'productId' => $ret['productId'] ?? '',
                            'productName' => $ret['productName'] ?? '',
                            'quantity' => $ret['quantity'] ?? 0,
                            'refundAmount' => $ret['refundAmount'] ?? 0,
                            'reason' => $ret['reason'] ?? null,
                            'createdAt' => $ret['createdAt'] ?? now()->toIso8601String(),
                            'userId' => $ret['userId'] ?? '',
                            'userName' => $ret['userName'] ?? null,
                            'timestamp' => $ret['timestamp'] ?? null,
                            'productCode' => $ret['productCode'] ?? null,
                            'wasDelivered' => $ret['wasDelivered'] ?? false,
                            'deliveryStatus' => $ret['deliveryStatus'] ?? null,
                        ]
                    );
                }
            }

            // 6. Sync Logs
            if (isset($payload['logs']) && is_array($payload['logs'])) {
                $ids = collect($payload['logs'])->pluck('id')->all();
                InventoryLog::whereNotIn('id', $ids)->delete();
                foreach ($payload['logs'] as $log) {
                    InventoryLog::updateOrCreate(
                        ['id' => $log['id']],
                        [
                            'productId' => $log['productId'] ?? '',
                            'type' => $log['type'] ?? 'stock-in',
                            'quantity' => $log['quantity'] ?? 0,
                            'userId' => $log['userId'] ?? '',
                            'notes' => $log['notes'] ?? null,
                            'timestamp' => $log['timestamp'] ?? now()->toIso8601String(),
                            'productCode' => $log['productCode'] ?? null,
                            'productName' => $log['productName'] ?? null,
                            'description' => $log['description'] ?? null,
                            'userName' => $log['userName'] ?? null,
                        ]
                    );
                }
            }

            // 7. Sync Activities
            if (isset($payload['activities']) && is_array($payload['activities'])) {
                $ids = collect($payload['activities'])->pluck('id')->all();
                Activity::whereNotIn('id', $ids)->delete();
                foreach ($payload['activities'] as $act) {
                    Activity::updateOrCreate(
                        ['id' => $act['id']],
                        [
                            'type' => $act['type'] ?? 'sale',
                            'description' => $act['description'] ?? '',
                            'userId' => $act['userId'] ?? '',
                            'userName' => $act['userName'] ?? '',
                            'timestamp' => $act['timestamp'] ?? now()->toIso8601String(),
                            'metadata' => $act['metadata'] ?? null,
                        ]
                    );
                }
            }

            // 8. Sync Settings
            if (isset($payload['settings']) && is_array($payload['settings'])) {
                $set = $payload['settings'];
                Setting::updateOrCreate(
                    ['id' => 1],
                    [
                        'businessName' => $set['businessName'] ?? 'HYSAM VENTURES',
                        'businessAddress' => $set['businessAddress'] ?? null,
                        'businessPhone' => $set['businessPhone'] ?? null,
                        'businessEmail' => $set['businessEmail'] ?? null,
                        'currency' => $set['currency'] ?? '₦',
                        'categories' => $set['categories'] ?? [],
                        'reportFooter' => $set['reportFooter'] ?? null,
                        'lowStockThreshold' => $set['lowStockThreshold'] ?? 5,
                        'transactionEditLimitDays' => $set['transactionEditLimitDays'] ?? 7,
                        'fontFamily' => $set['fontFamily'] ?? 'Inter',
                    ]
                );
            }

            // 9. Sync Custom Roles
            if (isset($payload['custom_roles']) && is_array($payload['custom_roles'])) {
                $ids = collect($payload['custom_roles'])->pluck('id')->all();
                \App\Models\CustomRole::whereNotIn('id', $ids)->delete();
                foreach ($payload['custom_roles'] as $r) {
                    \App\Models\CustomRole::updateOrCreate(
                        ['id' => $r['id']],
                        [
                            'label' => $r['label'] ?? '',
                            'description' => $r['description'] ?? null,
                            'badgeBg' => $r['badgeBg'] ?? null,
                            'badgeText' => $r['badgeText'] ?? null,
                            'badgeBorder' => $r['badgeBorder'] ?? null,
                            'isSystem' => $r['isSystem'] ?? false,
                            'modulePermissions' => $r['modulePermissions'] ?? null,
                            'allowedModules' => $r['allowedModules'] ?? null,
                        ]
                    );
                }
            }
        });

        return response()->json(['status' => 'ok']);
    }

    public function reset()
    {
        $currentUser = $this->checkAuth();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if ($currentUser->role !== 'admin') {
            return response()->json(['error' => 'Forbidden. Only administrators can reset data.'], 403);
        }

        DB::transaction(function () {
            SaleItem::query()->delete();
            Sale::query()->delete();
            Payment::query()->delete();
            SalesReturn::query()->delete();
            InventoryLog::query()->delete();
            Activity::query()->delete();
            Product::query()->delete();
            \App\Models\CustomRole::query()->delete();

            Product::create([
                'id' => 'p1',
                'code' => 'GEN-001',
                'name' => 'Industrial Generator',
                'size' => '500kVA',
                'brand' => 'Cummins',
                'description' => 'High capacity power backup generator',
                'category' => 'Power',
                'unitPrice' => 250000,
                'currentStock' => 0,
                'minStockLevel' => 2,
                'archived' => false,
                'userId' => 'admin-user-1',
                'updatedAt' => now()->toIso8601String(),
            ]);

            Product::create([
                'id' => 'p2',
                'code' => 'SOL-400',
                'name' => 'Solar Panel',
                'size' => '400W',
                'brand' => 'Jinko',
                'description' => 'Monocrystalline high-efficiency solar panel',
                'category' => 'Solar',
                'unitPrice' => 45000,
                'currentStock' => 0,
                'minStockLevel' => 10,
                'archived' => false,
                'userId' => 'admin-user-1',
                'updatedAt' => now()->toIso8601String(),
            ]);
        });

        return $this->get();
    }
}
