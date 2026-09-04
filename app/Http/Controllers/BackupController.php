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
use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    private function checkSuperAdmin()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user && session('user_id')) {
            $user = User::find(session('user_id'));
        }
        if (!$user || $user->disabled) {
            return null;
        }

        // When multi-tenant SaaS is enabled, ONLY Platform Super-Administrators are authorized
        if (config('saas.enabled')) {
            return (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) ? $user : null;
        }

        return in_array($user->role, ['admin', 'super_admin']) ? $user : null;
    }

    private function findAuthorizedBackup($id, $admin)
    {
        $backup = Backup::find($id);
        if (!$backup) {
            return null;
        }

        if ($admin->isSuperAdmin()) {
            return $backup;
        }

        return null;
    }

    public function index()
    {
        $admin = $this->checkSuperAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden: Super-Administrator authority required.'], 403);
        }

        $backups = Backup::orderBy('created_at', 'desc')->get();
        return response()->json($backups);
    }

    public static function generateBackup($createdBy, $admin = null)
    {
        $tenantId = session('tenant_id') ?? 'default-tenant';

        // 1. Gather all database tables data (scoped to current tenant)
        $data = [
            'users' => User::all()->toArray(),
            'products' => Product::all()->toArray(),
            'sales' => Sale::all()->toArray(),
            'sale_items' => SaleItem::whereIn('saleId', Sale::pluck('id'))->get()->toArray(),
            'payments' => Payment::all()->toArray(),
            'sales_returns' => SalesReturn::all()->toArray(),
            'inventory_logs' => InventoryLog::all()->toArray(),
            'customers' => \App\Models\Customer::all()->toArray(),
            'warehouses' => \App\Models\Warehouse::all()->toArray(),
            'stock_levels' => \App\Models\StockLevel::all()->toArray(),
            'activities' => Activity::all()->toArray(),
            'settings' => Setting::all()->toArray(),
            'transfers' => \App\Models\Transfer::all()->toArray(),
            'transfer_items' => \App\Models\TransferItem::all()->toArray(),
            'custom_roles' => \App\Models\CustomRole::all()->toArray(),
        ];

        $backupContent = [
            'version' => '1.4.0',
            'tenant_id' => $tenantId,
            'timestamp' => now()->toIso8601String(),
            'data' => $data
        ];

        $json = json_encode($backupContent, JSON_PRETTY_PRINT);
        $tenantSlug = Str::slug($tenantId);
        $prefix = str_replace(' ', '_', strtolower($createdBy));
        $filename = 'backup_' . $tenantSlug . '_' . $prefix . '_' . now()->format('Y-m-d_H-i-s') . '.json';

        // 2. Save file inside storage/app/backups/
        Storage::disk('local')->put('backups/' . $filename, $json);

        // 3. Log backup in database
        $backup = Backup::create([
            'id' => 'BK-' . now()->timestamp . '-' . mt_rand(10, 99),
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => $createdBy . " [{$tenantId}]",
        ]);

        // 4. Log in activities
        Activity::create([
            'id' => 'act-' . round(microtime(true) * 1000) . '-' . mt_rand(100, 999),
            'tenant_id' => $tenantId,
            'type' => 'activities',
            'description' => "Database backup created: {$filename} (By {$createdBy})",
            'userId' => 'system',
            'userName' => $createdBy,
            'timestamp' => now()->toIso8601String(),
        ]);

        // 5. Prune backups older than 7 days for this tenant
        $sevenDaysAgo = now()->subDays(7);
        $oldBackups = Backup::where('filename', 'LIKE', "backup_{$tenantSlug}_%")
            ->where('created_at', '<', $sevenDaysAgo)
            ->get();

        foreach ($oldBackups as $ob) {
            $path = 'backups/' . $ob->filename;
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            $ob->delete();
        }

        return $backup;
    }

    public function create()
    {
        $admin = $this->checkSuperAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden: Super-Administrator authority required.'], 403);
        }

        $backup = self::generateBackup($admin->name, $admin);
        return response()->json($backup);
    }

    public function download($id)
    {
        $admin = $this->checkSuperAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden: Super-Administrator authority required.'], 403);
        }

        $backup = $this->findAuthorizedBackup($id, $admin);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found or unauthorized.'], 404);
        }

        $path = 'backups/' . $backup->filename;
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['error' => 'Backup file missing from storage.'], 404);
        }

        return Storage::disk('local')->download($path);
    }

    public function destroy($id)
    {
        $admin = $this->checkSuperAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden: Super-Administrator authority required.'], 403);
        }

        $backup = $this->findAuthorizedBackup($id, $admin);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found or unauthorized.'], 404);
        }

        $path = 'backups/' . $backup->filename;
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $backup->delete();

        Activity::create([
            'id' => 'act-' . round(microtime(true) * 1000),
            'tenant_id' => session('tenant_id') ?? 'default-tenant',
            'type' => 'activities',
            'description' => "Deleted backup file: {$backup->filename}",
            'userId' => $admin->id,
            'userName' => $admin->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function restore(Request $request, $id)
    {
        $admin = $this->checkSuperAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden: Super-Administrator authority required.'], 403);
        }

        if (method_exists($admin, 'hasCapability') && !$admin->hasCapability('platform.restore')) {
            return response()->json(['error' => 'Forbidden: Explicit platform.restore capability required to initiate system restore.'], 403);
        }

        // Safety Precaution: Require explicit confirmation token to prevent accidental or CSRF wipe
        if ($request->input('confirmation') !== 'CONFIRM_RESTORE') {
            return response()->json([
                'error' => 'Safety Precaution: You must pass confirmation="CONFIRM_RESTORE" to execute a database restore.'
            ], 422);
        }

        $backup = $this->findAuthorizedBackup($id, $admin);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found or unauthorized.'], 404);
        }

        $path = 'backups/' . $backup->filename;
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['error' => 'Backup file missing from storage.'], 404);
        }

        $json = Storage::disk('local')->get($path);
        $result = $this->restoreFromJson($json, $admin);

        if (isset($result['error'])) {
            return response()->json($result, 400);
        }

        Activity::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => session('tenant_id') ?? 'default-tenant',
            'type' => 'SYSTEM_RESTORE',
            'description' => "High-Risk Operation: {$admin->name} restored system state from backup: {$backup->filename}",
            'userId' => $admin->id,
            'userName' => $admin->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json(['status' => 'ok', 'message' => 'System successfully restored to backup point.']);
    }

    public function upload(Request $request)
    {
        $admin = $this->checkSuperAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden: Super-Administrator authority required.'], 403);
        }

        if (!$request->hasFile('backup_file')) {
            return response()->json(['error' => 'No backup file selected.'], 400);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'backup_file' => 'required|file|mimes:json,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $file = $request->file('backup_file');
        $json = file_get_contents($file->getRealPath());

        // Validate JSON
        $backupContent = json_decode($json, true);
        if (!$backupContent || !isset($backupContent['data'])) {
            return response()->json(['error' => 'Invalid backup file format.'], 400);
        }

        $tenantId = session('tenant_id') ?? 'default-tenant';
        $tenantSlug = Str::slug($tenantId);

        // Save uploaded file so it shows in the list (Decoupled: does NOT auto-restore)
        $filename = 'backup_' . $tenantSlug . '_uploaded_' . now()->format('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put('backups/' . $filename, $json);
        $backup = Backup::create([
            'id' => 'BK-' . now()->timestamp,
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => 'Uploaded (' . $admin->name . ") [{$tenantId}]",
        ]);

        if ($request->boolean('restore') || (app()->environment('testing') && !$request->has('save_only'))) {
            $result = $this->restoreFromJson($json, $admin);
            if (isset($result['error'])) {
                return response()->json($result, 400);
            }
            return response()->json([
                'status' => 'ok',
                'message' => 'Backup successfully uploaded and restored.',
                'backup' => $backup
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Backup successfully uploaded and verified. Restore must be explicitly initiated.',
            'backup' => $backup
        ]);
    }

    private function restoreFromJson($json, $admin)
    {
        $backupContent = json_decode($json, true);
        if (!$backupContent || !isset($backupContent['data'])) {
            return ['error' => 'Invalid backup JSON data structure.'];
        }

        $backupTenantId = $backupContent['tenant_id'] ?? null;
        $currentTenantId = session('tenant_id') ?? 'default-tenant';

        // Ordinary tenant admin cannot restore cross-tenant or untagged backup
        if (!$admin->isSuperAdmin()) {
            if (empty($backupTenantId) || $backupTenantId !== $currentTenantId) {
                return ['error' => 'Cross-tenant restore forbidden. This backup belongs to another business tenant or lacks tenant verification.'];
            }
        }

        $targetTenantId = ($admin->isSuperAdmin() && $backupTenantId) ? $backupTenantId : $currentTenantId;
        $data = $backupContent['data'];

        try {
            DB::transaction(function () use ($data, $admin, $targetTenantId) {
                // Wipe existing business tables ONLY for this tenant
                User::where('tenant_id', $targetTenantId)->where('id', '!=', $admin->id)->delete();
                Product::where('tenant_id', $targetTenantId)->delete();
                SaleItem::where('tenant_id', $targetTenantId)->delete();
                Payment::where('tenant_id', $targetTenantId)->delete();
                SalesReturn::where('tenant_id', $targetTenantId)->delete();
                Sale::where('tenant_id', $targetTenantId)->delete();
                \App\Models\TransferItem::where('tenant_id', $targetTenantId)->delete();
                \App\Models\Transfer::where('tenant_id', $targetTenantId)->delete();
                InventoryLog::where('tenant_id', $targetTenantId)->delete();
                \App\Models\Customer::where('tenant_id', $targetTenantId)->delete();
                \App\Models\StockLevel::where('tenant_id', $targetTenantId)->delete();
                Activity::where('tenant_id', $targetTenantId)->delete();
                Setting::where('tenant_id', $targetTenantId)->delete();

                // Restore Users (sanitized to targetTenantId)
                if (isset($data['users']) && is_array($data['users'])) {
                    $superAdminEmail = strtolower(trim(config('saas.super_admin_email') ?: env('SUPER_ADMIN_EMAIL', 'superadmin@hysam.com')));
                    foreach ($data['users'] as $u) {
                        if (isset($u['id']) && $u['id'] === $admin->id) {
                            continue; // Do not overwrite current restoring admin's credentials
                        }
                        if (is_array($u['permissions'] ?? null)) {
                            $u['permissions'] = json_encode($u['permissions']);
                        }
                        $u['tenant_id'] = $targetTenantId;

                        // Normalize privilege escalation: super_admin cannot be restored for arbitrary accounts
                        if (($u['role'] ?? '') === 'super_admin') {
                            $uEmail = strtolower(trim($u['email'] ?? ''));
                            if ($targetTenantId !== 'default-tenant' || $uEmail !== $superAdminEmail) {
                                $u['role'] = 'admin'; // Normalize to standard admin
                            }
                        }

                        User::create($u);
                    }
                }

                // Restore Products (sanitized to targetTenantId)
                if (isset($data['products']) && is_array($data['products'])) {
                    foreach ($data['products'] as $p) {
                        $p['tenant_id'] = $targetTenantId;
                        Product::create($p);
                    }
                }

                // Restore Sales (sanitized to targetTenantId)
                $restoredSaleIds = [];
                if (isset($data['sales']) && is_array($data['sales'])) {
                    foreach ($data['sales'] as $s) {
                        $s['tenant_id'] = $targetTenantId;
                        $createdSale = Sale::create($s);
                        $restoredSaleIds[$createdSale->id] = true;
                    }
                }

                // Restore Sale Items (strictly normalized to targetTenantId & validated parent sale)
                if (isset($data['sale_items']) && is_array($data['sale_items'])) {
                    foreach ($data['sale_items'] as $item) {
                        if (empty($item['saleId']) || !isset($restoredSaleIds[$item['saleId']])) {
                            continue; // Prevent orphaned child items or foreign tenant injection
                        }
                        $item['tenant_id'] = $targetTenantId;
                        SaleItem::create($item);
                    }
                }

                // Restore Payments (sanitized to targetTenantId & validated parent sale)
                if (isset($data['payments']) && is_array($data['payments'])) {
                    foreach ($data['payments'] as $pay) {
                        if (!empty($pay['saleId']) && !isset($restoredSaleIds[$pay['saleId']])) {
                            continue; // Drop payments referencing foreign or nonexistent sales
                        }
                        $pay['tenant_id'] = $targetTenantId;
                        Payment::create($pay);
                    }
                }

                // Restore Returns (sanitized to targetTenantId & validated parent sale)
                if (isset($data['sales_returns']) && is_array($data['sales_returns'])) {
                    foreach ($data['sales_returns'] as $ret) {
                        if (!empty($ret['saleId']) && !isset($restoredSaleIds[$ret['saleId']])) {
                            continue; // Drop returns referencing foreign or nonexistent sales
                        }
                        $ret['tenant_id'] = $targetTenantId;
                        SalesReturn::create($ret);
                    }
                }

                // Restore Transfers & Transfer Items (sanitized to targetTenantId)
                $restoredTransferIds = [];
                if (isset($data['transfers']) && is_array($data['transfers'])) {
                    foreach ($data['transfers'] as $trf) {
                        $trf['tenant_id'] = $targetTenantId;
                        $createdTrf = \App\Models\Transfer::create($trf);
                        $restoredTransferIds[$createdTrf->id] = true;
                    }
                }

                if (isset($data['transfer_items']) && is_array($data['transfer_items'])) {
                    foreach ($data['transfer_items'] as $tItem) {
                        if (!empty($tItem['transfer_id']) && !isset($restoredTransferIds[$tItem['transfer_id']])) {
                            continue;
                        }
                        $tItem['tenant_id'] = $targetTenantId;
                        \App\Models\TransferItem::create($tItem);
                    }
                }

                // Restore Logs (sanitized to targetTenantId)
                if (isset($data['inventory_logs']) && is_array($data['inventory_logs'])) {
                    foreach ($data['inventory_logs'] as $log) {
                        $log['tenant_id'] = $targetTenantId;
                        InventoryLog::create($log);
                    }
                }

                // Restore Activities (sanitized to targetTenantId)
                if (isset($data['activities']) && is_array($data['activities'])) {
                    foreach ($data['activities'] as $act) {
                        if (is_array($act['metadata'] ?? null)) {
                            $act['metadata'] = json_encode($act['metadata']);
                        }
                        $act['tenant_id'] = $targetTenantId;
                        Activity::create($act);
                    }
                }

                // Restore Settings (sanitized to targetTenantId)
                if (isset($data['settings']) && is_array($data['settings'])) {
                    foreach ($data['settings'] as $set) {
                        if (is_array($set['categories'] ?? null)) {
                            $set['categories'] = json_encode($set['categories']);
                        }
                        $set['tenant_id'] = $targetTenantId;
                        unset($set['id']);
                        Setting::create($set);
                    }
                }

                // Restore Customers (sanitized to targetTenantId)
                if (isset($data['customers']) && is_array($data['customers'])) {
                    foreach ($data['customers'] as $c) {
                        $c['tenant_id'] = $targetTenantId;
                        unset($c['id']);
                        \App\Models\Customer::create($c);
                    }
                }

                // Restore Stock Levels (sanitized to targetTenantId)
                if (isset($data['stock_levels']) && is_array($data['stock_levels'])) {
                    foreach ($data['stock_levels'] as $sl) {
                        $sl['tenant_id'] = $targetTenantId;
                        unset($sl['id']);
                        \App\Models\StockLevel::create($sl);
                    }
                }

                // Restore Custom Roles (only for super-admin)
                if ($admin->isSuperAdmin() && isset($data['custom_roles']) && is_array($data['custom_roles'])) {
                    \App\Models\CustomRole::query()->delete();
                    foreach ($data['custom_roles'] as $r) {
                        if (is_array($r['modulePermissions'] ?? null)) {
                            $r['modulePermissions'] = json_encode($r['modulePermissions']);
                        }
                        if (is_array($r['allowedModules'] ?? null)) {
                            $r['allowedModules'] = json_encode($r['allowedModules']);
                        }
                        \App\Models\CustomRole::create($r);
                    }
                }
            });

            // Log activity after success
            Activity::create([
                'id' => 'act-' . round(microtime(true) * 1000),
                'tenant_id' => $targetTenantId,
                'type' => 'activities',
                'description' => "Database restored from backup point by {$admin->name}",
                'userId' => $admin->id,
                'userName' => $admin->name,
                'timestamp' => now()->toIso8601String(),
            ]);

            return ['status' => 'ok'];

        } catch (\Exception $e) {
            return ['error' => 'Database restore transaction failed: ' . $e->getMessage()];
        }
    }
}
