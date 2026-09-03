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
    private function checkAdmin()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user && session('user_id')) {
            $user = User::find(session('user_id'));
        }
        return ($user && !$user->disabled && in_array($user->role, ['admin', 'super_admin'])) ? $user : null;
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

        $tenantId = session('tenant_id') ?? 'default-tenant';

        // 1. Exact match on created_by tag [tenant_id]
        $matchesTag = false;
        if (preg_match('/\[([^\]]+)\]$/', $backup->created_by, $m)) {
            $matchesTag = ($m[1] === $tenantId);
        }

        if (!$matchesTag) {
            return null;
        }

        // 2. Exact match on disk payload tenant_id if physical file exists
        $filePath = 'backups/' . basename($backup->filename);
        if (Storage::disk('local')->exists($filePath)) {
            $meta = json_decode(Storage::disk('local')->get($filePath), true);
            if (isset($meta['tenant_id']) && $meta['tenant_id'] !== $tenantId) {
                return null;
            }
        }

        return $backup;
    }

    public function index()
    {
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if ($admin->isSuperAdmin()) {
            $backups = Backup::orderBy('created_at', 'desc')->get();
        } else {
            $tenantId = session('tenant_id') ?? 'default-tenant';
            $backups = Backup::orderBy('created_at', 'desc')->get()->filter(function ($b) use ($tenantId) {
                if (preg_match('/\[([^\]]+)\]$/', $b->created_by, $m)) {
                    return $m[1] === $tenantId;
                }
                return false;
            })->values();
        }

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
            'sale_items' => SaleItem::all()->toArray(),
            'payments' => Payment::all()->toArray(),
            'sales_returns' => SalesReturn::all()->toArray(),
            'inventory_logs' => InventoryLog::all()->toArray(),
            'activities' => Activity::all()->toArray(),
            'settings' => Setting::all()->toArray(),
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
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $backup = self::generateBackup($admin->name, $admin);
        return response()->json($backup);
    }

    public function download($id)
    {
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
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
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
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

    public function restore($id)
    {
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
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

        return response()->json(['status' => 'ok', 'message' => 'System successfully restored to backup point.']);
    }

    public function upload(Request $request)
    {
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if (!$request->hasFile('backup_file')) {
            return response()->json(['error' => 'No backup file selected.'], 400);
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

        // Save uploaded file so it shows in the list
        $filename = 'backup_' . $tenantSlug . '_uploaded_' . now()->format('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put('backups/' . $filename, $json);
        Backup::create([
            'id' => 'BK-' . now()->timestamp,
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => 'Uploaded (' . $admin->name . ") [{$tenantId}]",
        ]);

        $result = $this->restoreFromJson($json, $admin);

        if (isset($result['error'])) {
            return response()->json($result, 400);
        }

        return response()->json(['status' => 'ok', 'message' => 'Backup successfully uploaded and restored.']);
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
                Sale::where('tenant_id', $targetTenantId)->delete();
                SaleItem::whereHas('sale', function ($q) use ($targetTenantId) {
                    $q->where('tenant_id', $targetTenantId);
                })->delete();
                Payment::where('tenant_id', $targetTenantId)->delete();
                SalesReturn::where('tenant_id', $targetTenantId)->delete();
                InventoryLog::where('tenant_id', $targetTenantId)->delete();
                Activity::where('tenant_id', $targetTenantId)->delete();
                Setting::where('tenant_id', $targetTenantId)->delete();

                // Restore Users (sanitized to targetTenantId)
                if (isset($data['users']) && is_array($data['users'])) {
                    foreach ($data['users'] as $u) {
                        if (isset($u['id']) && $u['id'] === $admin->id) {
                            continue; // Do not overwrite current restoring admin's credentials
                        }
                        if (is_array($u['permissions'] ?? null)) {
                            $u['permissions'] = json_encode($u['permissions']);
                        }
                        $u['tenant_id'] = $targetTenantId;
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
                if (isset($data['sales']) && is_array($data['sales'])) {
                    foreach ($data['sales'] as $s) {
                        $s['tenant_id'] = $targetTenantId;
                        Sale::create($s);
                    }
                }

                // Restore Sale Items
                if (isset($data['sale_items']) && is_array($data['sale_items'])) {
                    foreach ($data['sale_items'] as $item) {
                        SaleItem::create($item);
                    }
                }

                // Restore Payments (sanitized to targetTenantId)
                if (isset($data['payments']) && is_array($data['payments'])) {
                    foreach ($data['payments'] as $pay) {
                        $pay['tenant_id'] = $targetTenantId;
                        Payment::create($pay);
                    }
                }

                // Restore Returns (sanitized to targetTenantId)
                if (isset($data['sales_returns']) && is_array($data['sales_returns'])) {
                    foreach ($data['sales_returns'] as $ret) {
                        $ret['tenant_id'] = $targetTenantId;
                        SalesReturn::create($ret);
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
