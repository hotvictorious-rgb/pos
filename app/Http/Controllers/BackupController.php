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

class BackupController extends Controller
{
    private function checkAdmin()
    {
        $userId = session('user_id');
        if (!$userId) {
            return null;
        }
        $user = User::find($userId);
        return ($user && !$user->disabled && $user->role === 'admin') ? $user : null;
    }

    public function index()
    {
        if (!$this->checkAdmin()) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $backups = Backup::orderBy('created_at', 'desc')->get();
        return response()->json($backups);
    }

    public static function generateBackup($createdBy)
    {
        // 1. Gather all database tables data
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
            'timestamp' => now()->toIso8601String(),
            'data' => $data
        ];

        $json = json_encode($backupContent, JSON_PRETTY_PRINT);
        $prefix = str_replace(' ', '_', strtolower($createdBy));
        $filename = 'backup_' . $prefix . '_' . now()->format('Y-m-d_H-i-s') . '.json';

        // 2. Save file inside storage/app/backups/
        Storage::disk('local')->put('backups/' . $filename, $json);

        // 3. Log backup in database
        $backup = Backup::create([
            'id' => 'BK-' . now()->timestamp . '-' . mt_rand(10, 99),
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => $createdBy,
        ]);

        // 4. Log in activities
        Activity::create([
            'id' => 'act-' . round(microtime(true) * 1000) . '-' . mt_rand(100, 999),
            'type' => 'activities',
            'description' => "Database backup created: {$filename} (By {$createdBy})",
            'userId' => 'system',
            'userName' => $createdBy,
            'timestamp' => now()->toIso8601String(),
        ]);

        // 5. Prune backups older than 7 days
        $sevenDaysAgo = now()->subDays(7);
        $oldBackups = Backup::where('created_at', '<', $sevenDaysAgo)->get();
        foreach ($oldBackups as $ob) {
            $path = 'backups/' . $ob->filename;
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            $ob->delete();

            Activity::create([
                'id' => 'act-' . round(microtime(true) * 1000) . '-' . mt_rand(100, 999),
                'type' => 'activities',
                'description' => "Auto-pruned old backup file: {$ob->filename} (Older than 7 days)",
                'userId' => 'system',
                'userName' => 'System',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return $backup;
    }

    public function create()
    {
        $admin = $this->checkAdmin();
        if (!$admin) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $backup = self::generateBackup($admin->name);
        return response()->json($backup);
    }

    public function download($id)
    {
        if (!$this->checkAdmin()) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $backup = Backup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found.'], 404);
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

        $backup = Backup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found.'], 404);
        }

        $path = 'backups/' . $backup->filename;
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $backup->delete();

        Activity::create([
            'id' => 'act-' . round(microtime(true) * 1000),
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

        $backup = Backup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found.'], 404);
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

        // Save uploaded file so it shows in the list
        $filename = 'backup_uploaded_' . now()->format('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put('backups/' . $filename, $json);
        Backup::create([
            'id' => 'BK-' . now()->timestamp,
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => 'Uploaded (' . $admin->name . ')',
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

        $data = $backupContent['data'];

        try {
            DB::transaction(function () use ($data) {
                // Wipe existing business tables
                User::query()->delete();
                Product::query()->delete();
                Sale::query()->delete();
                SaleItem::query()->delete();
                Payment::query()->delete();
                SalesReturn::query()->delete();
                InventoryLog::query()->delete();
                Activity::query()->delete();
                Setting::query()->delete();
                \App\Models\CustomRole::query()->delete();

                // Restore Users
                if (isset($data['users']) && is_array($data['users'])) {
                    foreach ($data['users'] as $u) {
                        if (is_array($u['permissions'])) {
                            $u['permissions'] = json_encode($u['permissions']);
                        }
                        User::create($u);
                    }
                }

                // Restore Products
                if (isset($data['products']) && is_array($data['products'])) {
                    foreach ($data['products'] as $p) {
                        Product::create($p);
                    }
                }

                // Restore Sales
                if (isset($data['sales']) && is_array($data['sales'])) {
                    foreach ($data['sales'] as $s) {
                        Sale::create($s);
                    }
                }

                // Restore Sale Items
                if (isset($data['sale_items']) && is_array($data['sale_items'])) {
                    foreach ($data['sale_items'] as $item) {
                        SaleItem::create($item);
                    }
                }

                // Restore Payments
                if (isset($data['payments']) && is_array($data['payments'])) {
                    foreach ($data['payments'] as $pay) {
                        Payment::create($pay);
                    }
                }

                // Restore Returns
                if (isset($data['sales_returns']) && is_array($data['sales_returns'])) {
                    foreach ($data['sales_returns'] as $ret) {
                        SalesReturn::create($ret);
                    }
                }

                // Restore Logs
                if (isset($data['inventory_logs']) && is_array($data['inventory_logs'])) {
                    foreach ($data['inventory_logs'] as $log) {
                        InventoryLog::create($log);
                    }
                }

                // Restore Activities
                if (isset($data['activities']) && is_array($data['activities'])) {
                    foreach ($data['activities'] as $act) {
                        if (is_array($act['metadata'])) {
                            $act['metadata'] = json_encode($act['metadata']);
                        }
                        Activity::create($act);
                    }
                }

                // Restore Settings
                if (isset($data['settings']) && is_array($data['settings'])) {
                    foreach ($data['settings'] as $set) {
                        if (is_array($set['categories'])) {
                            $set['categories'] = json_encode($set['categories']);
                        }
                        Setting::create($set);
                    }
                }

                // Restore Custom Roles
                if (isset($data['custom_roles']) && is_array($data['custom_roles'])) {
                    foreach ($data['custom_roles'] as $r) {
                        if (is_array($r['modulePermissions'])) {
                            $r['modulePermissions'] = json_encode($r['modulePermissions']);
                        }
                        if (is_array($r['allowedModules'])) {
                            $r['allowedModules'] = json_encode($r['allowedModules']);
                        }
                        \App\Models\CustomRole::create($r);
                    }
                }
            });

            // Log activity after success
            Activity::create([
                'id' => 'act-' . round(microtime(true) * 1000),
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
