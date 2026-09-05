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
        $userId = session('user_id') ?? \Illuminate\Support\Facades\Auth::id();
        if (!$userId) {
            return null;
        }
        $user = User::find($userId);
        return ($user && !$user->disabled) ? $user : null;
    }

    private function getSettings()
    {
        $settings = Setting::first();
        if ($settings) {
            return $settings;
        }

        $tenantId = session('tenant_id') ?? 'default-tenant';
        $tenantName = 'HYSAM VENTURES';
        if ($tenantId !== 'default-tenant') {
            $tenantObj = \App\Models\Tenant::find($tenantId);
            if ($tenantObj) {
                $tenantName = $tenantObj->name;
            }
        }

        $defaults = [
            'tenant_id' => $tenantId,
            'businessName' => $tenantName,
            'businessAddress' => '123 Main Street, Lagos, Nigeria',
            'businessPhone' => '+234 800 000 0000',
            'businessEmail' => 'info@hysam.com',
            'currency' => '₦',
            'categories' => ['Power', 'Solar', 'Battery', 'Inverter', 'Accessories', 'General'],
            'reportFooter' => 'Thank you for your business!',
            'lowStockThreshold' => 5,
            'transactionEditLimitDays' => 7,
            'fontFamily' => 'Inter'
        ];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $existing = Setting::first();
            if ($existing) {
                return $existing;
            }

            try {
                $nextId = (int) (Setting::withoutGlobalScopes()->max('id') ?? 0) + 1;
                return Setting::create(array_merge(['id' => $nextId], $defaults));
            } catch (\Illuminate\Database\QueryException $e) {
                $existing = Setting::first();
                if ($existing) {
                    return $existing;
                }
            }
        }

        return Setting::first();
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

        return response()->json([
            'users' => $users,
            'products' => $products,
            'sales' => $sales,
            'payments' => $payments,
            'logs' => $logs,
            'returns' => $returns,
            'activities' => $activities,
            'settings' => $settings,
        ]);
    }

    public function post(Request $request)
    {
        $currentUser = $this->checkAuth();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Architectural Invariant: VMarket POS is strictly 100% online.
        // Offline bulk synchronization is permanently disabled to guarantee that
        // StockService, row locks, authoritative pricing, and immutable ledgers cannot be bypassed.
        return response()->json([
            'error' => 'Forbidden. Offline data synchronization is disabled. VMarket POS is strictly online-only; all transactions must be submitted via authoritative business endpoints.'
        ], 403);
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

        // In SaaS mode, full system reset is strictly restricted to platform super-administrators
        if (config('saas.enabled') && !$currentUser->isSuperAdmin()) {
            return response()->json(['error' => 'Forbidden. System data reset is restricted to platform super-administrators.'], 403);
        }

        DB::transaction(function () {
            SaleItem::query()->delete();
            Sale::query()->delete();
            Payment::query()->delete();
            SalesReturn::query()->delete();
            InventoryLog::query()->delete();
            Activity::query()->delete();
            Product::query()->delete();

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
