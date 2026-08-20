<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Activity;
use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Display the comprehensive System Settings Hub.
     */
    public function index()
    {
        $settings = Setting::firstOrCreate(
            ['id' => 1],
            [
                'businessName' => 'Hysam Ventures',
                'businessAddress' => '12 Commercial Avenue, Lagos, Nigeria',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'admin@hysam.com',
                'currency' => '₦',
                'categories' => ['Groceries', 'Beverages', 'Electronics', 'Hardware', 'Household'],
                'reportFooter' => 'Thank you for your patronage! Goods sold in good condition cannot be returned after 3 days.',
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 0,
                'fontFamily' => 'Plus Jakarta Sans',
            ]
        );

        $warehouses = Warehouse::orderBy('id')->get();
        $backups = Backup::orderBy('created_at', 'desc')->get();

        return view('settings.index', compact('settings', 'warehouses', 'backups'));
    }

    /**
     * Update General Business & Receipt Settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'businessName' => 'required|string|max:150',
            'businessPhone' => 'nullable|string',
            'businessAddress' => 'nullable|string',
            'currency' => 'required|string|max:10',
            'lowStockThreshold' => 'required|numeric|min:1',
            'reportFooter' => 'nullable|string',
        ]);

        $settings = Setting::firstOrCreate(['id' => 1]);

        $categories = $request->categories ? array_filter(array_map('trim', explode(',', $request->categories))) : $settings->categories;

        $settings->update([
            'businessName' => $request->businessName,
            'businessPhone' => $request->businessPhone,
            'businessEmail' => $request->businessEmail,
            'businessAddress' => $request->businessAddress,
            'currency' => $request->currency,
            'categories' => $categories,
            'reportFooter' => $request->reportFooter,
            'lowStockThreshold' => (int) $request->lowStockThreshold,
        ]);

        $userName = Auth::user()->name ?? 'Auditor / Admin';

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'SETTINGS_UPDATED',
            'description' => "{$userName} updated business and receipt settings",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $userName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('settings.index')->with('success', '✓ Business and receipt settings saved successfully!');
    }

    /**
     * Add a New Branch Shop or Warehouse Location.
     */
    public function storeWarehouse(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:warehouses,code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'manager_name' => 'nullable|string',
        ]);

        Warehouse::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'address' => $request->address,
            'phone' => $request->phone,
            'manager_name' => $request->manager_name,
            'is_active' => true,
        ]);

        return redirect()->route('settings.index')->with('success', "✓ Branch shop '{$request->name}' added successfully!");
    }

    /**
     * Toggle Branch Location Active Status.
     */
    public function toggleWarehouse($id)
    {
        $wh = Warehouse::findOrFail($id);
        $wh->is_active = !$wh->is_active;
        $wh->save();

        return redirect()->route('settings.index')->with('success', "✓ Branch '{$wh->name}' status updated.");
    }
}
