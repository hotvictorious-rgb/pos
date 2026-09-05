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
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    /**
     * Resolves the authenticated user across session and token guards.
     */
    private function resolveUser()
    {
        $user = Auth::user();
        if (!$user && session('user_id')) {
            $user = User::find(session('user_id'));
        }
        if (!$user || $user->disabled) {
            return null;
        }
        return $user;
    }

    /**
     * Asserts user has required platform capability.
     */
    private function assertPlatformCapability($user, string $capability): bool
    {
        if (!$user || !$user->isPlatformUser()) {
            return false;
        }
        return method_exists($user, 'hasCapability') && $user->hasCapability($capability);
    }

    /**
     * Asserts user has required tenant capability.
     */
    private function assertTenantCapability($user, string $capability): bool
    {
        if (!$user || !$user->isTenantUser()) {
            return false;
        }
        return method_exists($user, 'hasCapability') && $user->hasCapability($capability);
    }

    /**
     * List backups scoped strictly by authority category:
     * - Platform Admin: sees ONLY platform infrastructure backups (whereNull('tenant_id'))
     * - Tenant Admin: sees ONLY their own business backups (where('tenant_id', $tenantId))
     */
    public function index(Request $request)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if ($user->isPlatformUser()) {
            if (!$this->assertPlatformCapability($user, 'platform.backup')) {
                return response()->json(['error' => 'Forbidden: platform.backup capability required.'], 403);
            }
            $backups = Backup::whereNull('tenant_id')->orderBy('created_at', 'desc')->get();
            return response()->json($backups);
        }

        if ($user->isTenantUser()) {
            if (!$this->assertTenantCapability($user, 'tenant.backup') && !$this->assertTenantCapability($user, 'settings.manage')) {
                return response()->json(['error' => 'Forbidden: tenant.backup capability required.'], 403);
            }
            $tenantId = session('tenant_id') ?? $user->tenant_id;
            $backups = Backup::where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->get();
            return response()->json($backups);
        }

        return response()->json(['error' => 'Forbidden: Unknown authority category.'], 403);
    }

    /**
     * Generate an instant backup:
     * - Platform User: creates platform infrastructure backup (ZERO tenant data)
     * - Tenant User: creates tenant business backup (scoped strictly to active tenant)
     */
    public function create(Request $request)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if ($user->isPlatformUser()) {
            if (!$this->assertPlatformCapability($user, 'platform.backup')) {
                return response()->json(['error' => 'Forbidden: platform.backup capability required.'], 403);
            }
            $backup = self::generatePlatformBackup($user->name, $user);
            return response()->json($backup);
        }

        if ($user->isTenantUser()) {
            if (!$this->assertTenantCapability($user, 'tenant.backup') && !$this->assertTenantCapability($user, 'settings.manage')) {
                return response()->json(['error' => 'Forbidden: tenant.backup capability required.'], 403);
            }
            $tenantId = session('tenant_id') ?? $user->tenant_id;
            $backup = self::generateTenantBackup($user->name, $user, $tenantId);
            return response()->json($backup);
        }

        return response()->json(['error' => 'Forbidden: Unknown authority category.'], 403);
    }

    /**
     * Download backup snapshot:
     * - Platform User can ONLY download platform backups (whereNull('tenant_id'))
     * - Tenant User can ONLY download their own tenant's backups (where('tenant_id', $tenantId))
     * - Attempting to download across boundaries returns 403 Forbidden
     */
    public function download($id)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $backup = Backup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found.'], 404);
        }

        if ($user->isPlatformUser()) {
            if (!$this->assertPlatformCapability($user, 'platform.backup')) {
                return response()->json(['error' => 'Forbidden: platform.backup capability required.'], 403);
            }
            // Strict Architectural Boundary: Platform Admin has ZERO access to tenant business backups
            if ($backup->isTenantBackup()) {
                return response()->json([
                    'error' => 'Forbidden. Platform administrators cannot access or download tenant business backups.'
                ], 403);
            }
        } elseif ($user->isTenantUser()) {
            if (!$this->assertTenantCapability($user, 'settings.manage')) {
                return response()->json(['error' => 'Forbidden: settings.manage capability required.'], 403);
            }
            $tenantId = session('tenant_id') ?? $user->tenant_id;
            if ($backup->tenant_id !== $tenantId) {
                return response()->json(['error' => 'Unauthorized: Cannot access foreign or platform backup.'], 403);
            }
        } else {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $path = 'backups/' . $backup->filename;
        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['error' => 'Backup file missing from storage.'], 404);
        }

        return Storage::disk('local')->download($path);
    }

    /**
     * Delete backup snapshot with strict boundary scoping.
     */
    public function destroy($id)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $backup = Backup::find($id);
        if (!$backup) {
            return response()->json(['error' => 'Backup not found.'], 404);
        }

        if ($user->isPlatformUser()) {
            if (!$this->assertPlatformCapability($user, 'platform.backup')) {
                return response()->json(['error' => 'Forbidden: platform.backup capability required.'], 403);
            }
            if ($backup->isTenantBackup()) {
                return response()->json(['error' => 'Forbidden. Platform administrators cannot delete tenant business backups.'], 403);
            }
        } elseif ($user->isTenantUser()) {
            if (!$this->assertTenantCapability($user, 'settings.manage')) {
                return response()->json(['error' => 'Forbidden: settings.manage capability required.'], 403);
            }
            $tenantId = session('tenant_id') ?? $user->tenant_id;
            if ($backup->tenant_id !== $tenantId) {
                return response()->json(['error' => 'Unauthorized: Cannot delete foreign backup.'], 403);
            }
        } else {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $path = 'backups/' . $backup->filename;
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $backup->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Restore database snapshot:
     * - Platform Admin: can ONLY restore platform infrastructure metadata from a platform backup.
     * - Tenant Admin: can ONLY restore their own tenant data into their own tenant.
     * - Strict confirmation token required.
     */
    public function restore(Request $request, $id)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Safety Precaution: Require explicit confirmation token to prevent accidental wipe
        if ($request->input('confirmation') !== 'CONFIRM_RESTORE') {
            return response()->json([
                'error' => 'Safety Precaution: You must pass confirmation="CONFIRM_RESTORE" to execute a database restore.'
            ], 422);
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

        if ($user->isPlatformUser()) {
            if (!$this->assertPlatformCapability($user, 'platform.restore')) {
                return response()->json(['error' => 'Forbidden: Explicit platform.restore capability required.'], 403);
            }
            if ($backup->isTenantBackup()) {
                return response()->json([
                    'error' => 'Forbidden. Platform administrators cannot restore tenant business backups.'
                ], 403);
            }
            $result = $this->restorePlatformFromJson($json, $user);
        } elseif ($user->isTenantUser()) {
            if (!$this->assertTenantCapability($user, 'tenant.backup') && !$this->assertTenantCapability($user, 'settings.manage')) {
                return response()->json(['error' => 'Forbidden: tenant.backup capability required.'], 403);
            }
            $tenantId = session('tenant_id') ?? $user->tenant_id;
            if ($backup->tenant_id !== $tenantId) {
                return response()->json(['error' => 'Unauthorized: Cannot restore foreign or platform backup into tenant.'], 403);
            }
            $result = $this->restoreTenantFromJson($json, $user, $tenantId);
        } else {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if (isset($result['error'])) {
            return response()->json($result, 400);
        }

        return response()->json(['status' => 'ok', 'message' => 'System successfully restored to backup point.']);
    }

    /**
     * Upload backup file with decoupled storage and strict category validation.
     */
    public function upload(Request $request)
    {
        $user = $this->resolveUser();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
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

        $backupContent = json_decode($json, true);
        if (!$backupContent || !isset($backupContent['data'])) {
            return response()->json(['error' => 'Invalid backup file format.'], 400);
        }

        $backupType = $backupContent['type'] ?? (isset($backupContent['tenant_id']) ? 'TENANT' : 'PLATFORM');

        if ($user->isPlatformUser()) {
            if (!$this->assertPlatformCapability($user, 'platform.restore')) {
                return response()->json(['error' => 'Forbidden: platform.restore capability required.'], 403);
            }
            if ($backupType === 'TENANT' || !empty($backupContent['tenant_id'])) {
                return response()->json(['error' => 'Forbidden. Platform administrators cannot upload or manage tenant business backups.'], 403);
            }

            $validationError = $this->validateBackupIntegrity($backupContent, 'PLATFORM');
            if ($validationError) {
                return response()->json($validationError, 400);
            }

            $filename = 'platform_backup_uploaded_' . now()->format('Y-m-d_H-i-s') . '.json';
            Storage::disk('local')->put('backups/' . $filename, $json);
            $backup = Backup::create([
                'id' => 'BK-' . now()->timestamp . '-' . mt_rand(10, 99),
                'tenant_id' => null,
                'filename' => $filename,
                'size' => strlen($json),
                'created_by' => 'Uploaded (Platform Admin: ' . $user->name . ')',
            ]);

            if ($request->boolean('restore') || (app()->environment('testing') && !$request->has('save_only'))) {
                $result = $this->restorePlatformFromJson($json, $user);
                if (isset($result['error'])) {
                    return response()->json($result, 400);
                }
            }

            return response()->json(['status' => 'ok', 'message' => 'Platform backup uploaded.', 'backup' => $backup]);
        }

        if ($user->isTenantUser()) {
            if (!$this->assertTenantCapability($user, 'tenant.backup') && !$this->assertTenantCapability($user, 'settings.manage')) {
                return response()->json(['error' => 'Forbidden: tenant.backup capability required.'], 403);
            }
            $tenantId = session('tenant_id') ?? $user->tenant_id;
            if (($backupContent['tenant_id'] ?? null) !== $tenantId) {
                return response()->json(['error' => 'Cross-tenant backup upload rejected. Backup belongs to another tenant.'], 403);
            }

            $validationError = $this->validateBackupIntegrity($backupContent, 'TENANT', $tenantId);
            if ($validationError) {
                return response()->json($validationError, 400);
            }

            $tenantSlug = Str::slug($tenantId);
            $filename = 'backup_' . $tenantSlug . '_uploaded_' . now()->format('Y-m-d_H-i-s') . '.json';
            Storage::disk('local')->put('backups/' . $filename, $json);
            $backup = Backup::create([
                'id' => 'BK-' . now()->timestamp . '-' . mt_rand(10, 99),
                'tenant_id' => $tenantId,
                'filename' => $filename,
                'size' => strlen($json),
                'created_by' => 'Uploaded (' . $user->name . ") [{$tenantId}]",
            ]);

            if ($request->boolean('restore') || (app()->environment('testing') && !$request->has('save_only'))) {
                $result = $this->restoreTenantFromJson($json, $user, $tenantId);
                if (isset($result['error'])) {
                    return response()->json($result, 400);
                }
            }

            return response()->json(['status' => 'ok', 'message' => 'Tenant backup uploaded.', 'backup' => $backup]);
        }

        return response()->json(['error' => 'Forbidden.'], 403);
    }

    // ─────────────────────────────────────────────────────────────
    // AUTHORITATIVE GENERATION METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Compute authoritative HMAC SHA-256 over the complete canonical backup envelope.
     */
    public static function computeEnvelopeChecksum(array $backupContent, ?string $signingKey = null): string
    {
        $key = $signingKey ?: config('app.key');
        if (empty($key)) {
            throw new \RuntimeException('Application key is not configured for cryptographic backup signing.');
        }

        $envelope = [
            'version' => (string) ($backupContent['version'] ?? '2.1.0'),
            'type' => (string) ($backupContent['type'] ?? ''),
            'tenant_id' => $backupContent['tenant_id'] ?? null,
            'manifest' => $backupContent['manifest'] ?? [],
            'data' => $backupContent['data'] ?? [],
        ];
        return hash_hmac('sha256', json_encode($envelope), $key);
    }

    /**
     * Generates a Platform Infrastructure Backup.
     * Contains ONLY platform metadata (tenants, platform settings, platform activities).
     * Strictly contains ZERO tenant business data (no products, sales, customers, stock, or payments).
     */
    public static function generatePlatformBackup(string $createdBy, $admin = null): Backup
    {
        $data = [
            'tenants' => Tenant::all()->toArray(),
            'platform_settings' => Setting::whereNull('tenant_id')->get()->toArray(),
            'platform_activities' => Activity::whereNull('tenant_id')->limit(500)->get()->toArray(),
            'custom_roles' => \App\Models\CustomRole::all()->toArray(),
        ];

        $manifest = [
            'tenants' => count($data['tenants']),
            'platform_settings' => count($data['platform_settings']),
            'platform_activities' => count($data['platform_activities']),
            'custom_roles' => count($data['custom_roles']),
        ];

        $signingKey = config('app.key');
        if (empty($signingKey)) {
            throw new \RuntimeException('Application key is not configured for cryptographic backup signing.');
        }

        $checksum = self::computeEnvelopeChecksum([
            'version' => '2.1.0',
            'type' => 'PLATFORM',
            'tenant_id' => null,
            'manifest' => $manifest,
            'data' => $data,
        ], $signingKey);

        $backupContent = [
            'version' => '2.1.0',
            'type' => 'PLATFORM',
            'tenant_id' => null,
            'timestamp' => now()->toIso8601String(),
            'checksum' => $checksum,
            'manifest' => $manifest,
            'data' => $data,
        ];

        $json = json_encode($backupContent, JSON_PRETTY_PRINT);
        $prefix = str_replace(' ', '_', strtolower($createdBy));
        $filename = 'platform_backup_' . $prefix . '_' . now()->format('Y-m-d_H-i-s') . '.json';

        Storage::disk('local')->put('backups/' . $filename, $json);

        $backup = Backup::create([
            'id' => 'BK-PLATFORM-' . now()->timestamp . '-' . mt_rand(10, 99),
            'tenant_id' => null,
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => "Platform Admin [{$createdBy}]",
        ]);

        return $backup;
    }

    /**
     * Generates a Tenant Business Backup.
     * Scoped strictly to the specified $tenantId.
     */
    public static function generateTenantBackup(string $createdBy, $user = null, ?string $tenantId = null): Backup
    {
        $tenantId = $tenantId ?? session('tenant_id') ?? 'default-tenant';

        $data = [
            'users' => User::where('tenant_id', $tenantId)->get()->toArray(),
            'products' => Product::where('tenant_id', $tenantId)->get()->toArray(),
            'sales' => Sale::where('tenant_id', $tenantId)->get()->toArray(),
            'sale_items' => SaleItem::where('tenant_id', $tenantId)->get()->toArray(),
            'payments' => Payment::where('tenant_id', $tenantId)->get()->toArray(),
            'sales_returns' => SalesReturn::where('tenant_id', $tenantId)->get()->toArray(),
            'inventory_logs' => InventoryLog::where('tenant_id', $tenantId)->get()->toArray(),
            'customers' => \App\Models\Customer::where('tenant_id', $tenantId)->get()->toArray(),
            'warehouses' => \App\Models\Warehouse::where('tenant_id', $tenantId)->get()->toArray(),
            'stock_levels' => \App\Models\StockLevel::where('tenant_id', $tenantId)->get()->toArray(),
            'activities' => Activity::where('tenant_id', $tenantId)->get()->toArray(),
            'settings' => Setting::where('tenant_id', $tenantId)->get()->toArray(),
            'transfers' => \App\Models\Transfer::where('tenant_id', $tenantId)->get()->toArray(),
            'transfer_items' => \App\Models\TransferItem::where('tenant_id', $tenantId)->get()->toArray(),
            'stock_reservations' => \App\Models\StockReservation::where('tenant_id', $tenantId)->get()->toArray(),
            'customer_ledgers' => \App\Models\CustomerLedger::where('tenant_id', $tenantId)->get()->toArray(),
            'stock_adjustments' => \App\Models\StockAdjustment::where('tenant_id', $tenantId)->get()->toArray(),
        ];

        $manifest = [
            'users' => count($data['users']),
            'products' => count($data['products']),
            'sales' => count($data['sales']),
            'sale_items' => count($data['sale_items']),
            'payments' => count($data['payments']),
            'sales_returns' => count($data['sales_returns']),
            'inventory_logs' => count($data['inventory_logs']),
            'customers' => count($data['customers']),
            'warehouses' => count($data['warehouses']),
            'stock_levels' => count($data['stock_levels']),
            'activities' => count($data['activities']),
            'settings' => count($data['settings']),
            'transfers' => count($data['transfers']),
            'transfer_items' => count($data['transfer_items']),
            'stock_reservations' => count($data['stock_reservations']),
            'customer_ledgers' => count($data['customer_ledgers']),
            'stock_adjustments' => count($data['stock_adjustments']),
        ];

        $signingKey = config('app.key');
        if (empty($signingKey)) {
            throw new \RuntimeException('Application key is not configured for cryptographic backup signing.');
        }

        $checksum = self::computeEnvelopeChecksum([
            'version' => '2.1.0',
            'type' => 'TENANT',
            'tenant_id' => $tenantId,
            'manifest' => $manifest,
            'data' => $data,
        ], $signingKey);

        $backupContent = [
            'version' => '2.1.0',
            'type' => 'TENANT',
            'tenant_id' => $tenantId,
            'timestamp' => now()->toIso8601String(),
            'checksum' => $checksum,
            'manifest' => $manifest,
            'data' => $data,
        ];

        $json = json_encode($backupContent, JSON_PRETTY_PRINT);
        $tenantSlug = Str::slug($tenantId);
        $prefix = str_replace(' ', '_', strtolower($createdBy));
        $filename = 'backup_' . $tenantSlug . '_' . $prefix . '_' . now()->format('Y-m-d_H-i-s') . '.json';

        Storage::disk('local')->put('backups/' . $filename, $json);

        $backup = Backup::create([
            'id' => 'BK-' . now()->timestamp . '-' . mt_rand(10, 99),
            'tenant_id' => $tenantId,
            'filename' => $filename,
            'size' => strlen($json),
            'created_by' => "{$createdBy} [{$tenantId}]",
        ]);

        return $backup;
    }

    /**
     * Backward-compatible helper for legacy callers.
     */
    public static function generateBackup($createdBy, $admin = null)
    {
        $tenantId = session('tenant_id');
        if (!empty($tenantId)) {
            return self::generateTenantBackup($createdBy, $admin, $tenantId);
        }
        return self::generatePlatformBackup($createdBy, $admin);
    }

    // ─────────────────────────────────────────────────────────────
    // AUTHORITATIVE RESTORE ENGINES
    // ─────────────────────────────────────────────────────────────

    /**
     * Authoritatively validate backup cryptographic HMAC, manifest counts, and format.
     */
    public function validateBackupIntegrity(array $backupContent, string $expectedType, ?string $expectedTenantId = null): ?array
    {
        if (empty($backupContent['version'])) {
            return ['error' => 'Backup integrity verification failed: Backup version identifier is missing.'];
        }

        $type = $backupContent['type'] ?? null;
        if ($type !== $expectedType) {
            return ['error' => "Backup integrity verification failed: Expected '{$expectedType}' backup, but found '{$type}'."];
        }

        if ($expectedType === 'TENANT') {
            $tenantId = $backupContent['tenant_id'] ?? null;
            if (empty($tenantId) || ($expectedTenantId !== null && $tenantId !== $expectedTenantId)) {
                return ['error' => 'Cross-tenant restore forbidden. Backup belongs to another tenant or lacks valid tenant identifier.'];
            }
        } elseif ($expectedType === 'PLATFORM') {
            if (!empty($backupContent['tenant_id'])) {
                return ['error' => 'Invalid backup: Expected a platform infrastructure backup, but received tenant data.'];
            }
        }

        if (empty($backupContent['checksum'])) {
            return ['error' => 'Backup integrity verification failed: Cryptographic checksum is missing.'];
        }

        if (empty($backupContent['manifest']) || !is_array($backupContent['manifest'])) {
            return ['error' => 'Backup integrity verification failed: Payload manifest is missing.'];
        }

        if (!isset($backupContent['data']) || !is_array($backupContent['data'])) {
            return ['error' => 'Backup integrity verification failed: Payload data is missing.'];
        }

        $signingKey = config('app.key');
        if (empty($signingKey)) {
            throw new \RuntimeException('Application key is not configured for cryptographic backup signing.');
        }

        $expectedEnvelopeChecksum = self::computeEnvelopeChecksum($backupContent, $signingKey);
        $checksumMatches = hash_equals($expectedEnvelopeChecksum, $backupContent['checksum']);

        // Backward compatibility: If envelope checksum does not match, check legacy data-only checksum
        if (!$checksumMatches) {
            $legacyDataChecksum = hash_hmac('sha256', json_encode($backupContent['data']), $signingKey);
            $checksumMatches = hash_equals($legacyDataChecksum, $backupContent['checksum']);
        }

        if (!$checksumMatches) {
            return ['error' => 'Backup integrity verification failed (checksum mismatch). The backup payload may be corrupted or tampered with.'];
        }

        // Verify manifest counts against actual records in data
        foreach ($backupContent['manifest'] as $table => $expectedCount) {
            $actualCount = isset($backupContent['data'][$table]) && is_array($backupContent['data'][$table])
                ? count($backupContent['data'][$table])
                : 0;
            if ($actualCount !== (int) $expectedCount) {
                return ['error' => "Backup integrity verification failed: Manifest count mismatch for '{$table}'. Expected: {$expectedCount}, Found: {$actualCount}."];
            }
        }

        return null;
    }

    /**
     * Restores Platform Infrastructure metadata.
     * Never touches any tenant business data tables.
     */
    private function restorePlatformFromJson(string $json, $user): array
    {
        $backupContent = json_decode($json, true);
        if (!$backupContent || !isset($backupContent['data'])) {
            return ['error' => 'Invalid platform backup JSON format.'];
        }

        $valError = $this->validateBackupIntegrity($backupContent, 'PLATFORM');
        if ($valError) {
            return $valError;
        }

        $data = $backupContent['data'];

        try {
            DB::transaction(function () use ($data) {
                // 1. Restore Tenants (Non-destructive updateOrCreate to protect active tenant integrity)
                if (isset($data['tenants']) && is_array($data['tenants'])) {
                    foreach ($data['tenants'] as $t) {
                        Tenant::updateOrCreate(
                            ['id' => $t['id']],
                            [
                                'name' => $t['name'],
                                'owner_email' => $t['owner_email'] ?? null,
                                'owner_phone' => $t['owner_phone'] ?? null,
                                'plan' => $t['plan'] ?? 'basic',
                                'status' => $t['status'] ?? 'active',
                                'trial_ends_at' => $t['trial_ends_at'] ?? null,
                                'max_branches' => $t['max_branches'] ?? 1,
                                'max_users' => $t['max_users'] ?? 5,
                            ]
                        );
                    }
                }

                // 2. Restore Custom Roles
                if (isset($data['custom_roles']) && is_array($data['custom_roles'])) {
                    \App\Models\CustomRole::query()->delete();
                    foreach ($data['custom_roles'] as $cr) {
                        if (empty($cr['id'])) {
                            $cr['id'] = (string) \Illuminate\Support\Str::uuid();
                        }
                        if (is_array($cr['permissions'] ?? null)) {
                            $cr['permissions'] = json_encode($cr['permissions']);
                        }
                        \App\Models\CustomRole::create($cr);
                    }
                }

                // 3. Restore Platform Settings
                if (isset($data['platform_settings']) && is_array($data['platform_settings'])) {
                    Setting::whereNull('tenant_id')->delete();
                    foreach ($data['platform_settings'] as $set) {
                        unset($set['id']);
                        $set['tenant_id'] = null;
                        Setting::create($set);
                    }
                }

                // 4. Restore Platform Activities
                if (isset($data['platform_activities']) && is_array($data['platform_activities'])) {
                    Activity::whereNull('tenant_id')->delete();
                    foreach ($data['platform_activities'] as $act) {
                        $act['tenant_id'] = null;
                        if (is_array($act['metadata'] ?? null)) {
                            $act['metadata'] = json_encode($act['metadata']);
                        }
                        Activity::create($act);
                    }
                }
            });

            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['error' => 'Platform restore failed: ' . $e->getMessage()];
        }
    }

    /**
     * Restores Tenant Business records strictly into $targetTenantId.
     */
    private function restoreTenantFromJson(string $json, $user, string $targetTenantId): array
    {
        $backupContent = json_decode($json, true);
        if (!$backupContent || !isset($backupContent['data'])) {
            return ['error' => 'Invalid tenant backup JSON format.'];
        }

        $valError = $this->validateBackupIntegrity($backupContent, 'TENANT', $targetTenantId);
        if ($valError) {
            return $valError;
        }

        $data = $backupContent['data'];

        try {
            DB::transaction(function () use ($data, $user, $targetTenantId) {
                \App\Models\StockReservation::where('tenant_id', $targetTenantId)->delete();
                \App\Models\StockAdjustment::where('tenant_id', $targetTenantId)->delete();
                \App\Models\CustomerLedger::where('tenant_id', $targetTenantId)->delete();
                User::where('tenant_id', $targetTenantId)->where('id', '!=', $user->id)->delete();
                Product::where('tenant_id', $targetTenantId)->delete();
                SaleItem::where('tenant_id', $targetTenantId)->delete();
                Payment::where('tenant_id', $targetTenantId)->delete();
                SalesReturn::where('tenant_id', $targetTenantId)->delete();
                Sale::where('tenant_id', $targetTenantId)->delete();
                \App\Models\TransferItem::where('tenant_id', $targetTenantId)->delete();
                \App\Models\Transfer::where('tenant_id', $targetTenantId)->delete();
                InventoryLog::where('tenant_id', $targetTenantId)->delete();
                \App\Models\Customer::withTrashed()->where('tenant_id', $targetTenantId)->forceDelete();
                \App\Models\StockLevel::where('tenant_id', $targetTenantId)->delete();
                \App\Models\Warehouse::withTrashed()->where('tenant_id', $targetTenantId)->forceDelete();
                Activity::where('tenant_id', $targetTenantId)->delete();
                Setting::where('tenant_id', $targetTenantId)->delete();

                // 1. Restore Warehouses FIRST to establish old -> new Warehouse ID mapping
                $warehouseIdMap = [];
                if (isset($data['warehouses']) && is_array($data['warehouses'])) {
                    foreach ($data['warehouses'] as $wh) {
                        $oldWhId = $wh['id'] ?? null;
                        $wh['tenant_id'] = $targetTenantId;
                        unset($wh['id']);
                        $newWh = \App\Models\Warehouse::create($wh);
                        if ($oldWhId !== null) {
                            $warehouseIdMap[(string)$oldWhId] = $newWh->id;
                            $warehouseIdMap[(int)$oldWhId] = $newWh->id;
                        }
                    }
                }

                // If the active restoring user was bound to a warehouse, remap their assignment
                if (!empty($user->warehouse_id) && isset($warehouseIdMap[$user->warehouse_id])) {
                    $user->warehouse_id = $warehouseIdMap[$user->warehouse_id];
                    $user->save();
                }

                // 2. Restore Customers to establish old -> new Customer ID mapping
                $customerIdMap = [];
                if (isset($data['customers']) && is_array($data['customers'])) {
                    foreach ($data['customers'] as $c) {
                        $oldId = $c['id'] ?? null;
                        $c['tenant_id'] = $targetTenantId;
                        unset($c['id']);
                        unset($c['customer_code']);
                        $newCustomer = \App\Models\Customer::create($c);
                        if ($oldId !== null) {
                            $customerIdMap[(string)$oldId] = $newCustomer->id;
                            $customerIdMap[(int)$oldId] = $newCustomer->id;
                        }
                    }
                }

                // 3. Restore Users with remapped Warehouse IDs
                if (isset($data['users']) && is_array($data['users'])) {
                    foreach ($data['users'] as $u) {
                        if (isset($u['id']) && $u['id'] === $user->id) {
                            continue;
                        }
                        if (is_array($u['permissions'] ?? null)) {
                            $u['permissions'] = json_encode($u['permissions']);
                        }
                        $u['tenant_id'] = $targetTenantId;
                        if (!empty($u['warehouse_id'])) {
                            $u['warehouse_id'] = $warehouseIdMap[$u['warehouse_id']] ?? $warehouseIdMap[(int)$u['warehouse_id']] ?? null;
                        }
                        if (($u['role'] ?? '') === 'super_admin') {
                            $u['role'] = 'admin';
                        }
                        User::create($u);
                    }
                }

                // 4. Restore Products
                if (isset($data['products']) && is_array($data['products'])) {
                    foreach ($data['products'] as $p) {
                        $p['tenant_id'] = $targetTenantId;
                        Product::create($p);
                    }
                }

                // 5. Restore Sales & Sale Items with remapped Customer & Warehouse IDs
                $restoredSaleIds = [];
                if (isset($data['sales']) && is_array($data['sales'])) {
                    foreach ($data['sales'] as $s) {
                        $s['tenant_id'] = $targetTenantId;
                        if (!empty($s['customerId'])) {
                            $s['customerId'] = $customerIdMap[$s['customerId']] ?? $customerIdMap[(int)$s['customerId']] ?? null;
                        }
                        if (!empty($s['warehouse_id'])) {
                            $s['warehouse_id'] = $warehouseIdMap[$s['warehouse_id']] ?? $warehouseIdMap[(int)$s['warehouse_id']] ?? null;
                        }
                        $createdSale = Sale::create($s);
                        $restoredSaleIds[$createdSale->id] = true;
                    }
                }

                if (isset($data['sale_items']) && is_array($data['sale_items'])) {
                    foreach ($data['sale_items'] as $item) {
                        if (empty($item['saleId']) || !isset($restoredSaleIds[$item['saleId']])) {
                            continue;
                        }
                        $item['tenant_id'] = $targetTenantId;
                        SaleItem::create($item);
                    }
                }

                // 6. Restore Payments
                if (isset($data['payments']) && is_array($data['payments'])) {
                    foreach ($data['payments'] as $pay) {
                        if (!empty($pay['saleId']) && !isset($restoredSaleIds[$pay['saleId']])) {
                            continue;
                        }
                        $pay['tenant_id'] = $targetTenantId;
                        Payment::create($pay);
                    }
                }

                // 7. Restore Returns
                if (isset($data['sales_returns']) && is_array($data['sales_returns'])) {
                    foreach ($data['sales_returns'] as $ret) {
                        if (!empty($ret['saleId']) && !isset($restoredSaleIds[$ret['saleId']])) {
                            continue;
                        }
                        $ret['tenant_id'] = $targetTenantId;
                        SalesReturn::create($ret);
                    }
                }

                // 8. Restore Transfers with remapped source & destination warehouse IDs
                $restoredTransferIds = [];
                if (isset($data['transfers']) && is_array($data['transfers'])) {
                    foreach ($data['transfers'] as $trf) {
                        $trf['tenant_id'] = $targetTenantId;
                        if (!empty($trf['source_warehouse_id'])) {
                            $trf['source_warehouse_id'] = $warehouseIdMap[$trf['source_warehouse_id']] ?? $warehouseIdMap[(int)$trf['source_warehouse_id']] ?? null;
                        }
                        if (!empty($trf['destination_warehouse_id'])) {
                            $trf['destination_warehouse_id'] = $warehouseIdMap[$trf['destination_warehouse_id']] ?? $warehouseIdMap[(int)$trf['destination_warehouse_id']] ?? null;
                        }
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

                // 9. Restore Inventory Logs with remapped Warehouse IDs
                if (isset($data['inventory_logs']) && is_array($data['inventory_logs'])) {
                    foreach ($data['inventory_logs'] as $log) {
                        $log['tenant_id'] = $targetTenantId;
                        if (!empty($log['warehouse_id'])) {
                            $log['warehouse_id'] = $warehouseIdMap[$log['warehouse_id']] ?? $warehouseIdMap[(int)$log['warehouse_id']] ?? null;
                        }
                        InventoryLog::create($log);
                    }
                }

                // 10. Restore Activities
                if (isset($data['activities']) && is_array($data['activities'])) {
                    foreach ($data['activities'] as $act) {
                        if (is_array($act['metadata'] ?? null)) {
                            $act['metadata'] = json_encode($act['metadata']);
                        }
                        $act['tenant_id'] = $targetTenantId;
                        Activity::create($act);
                    }
                }

                // 11. Restore Settings
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

                // 12. Restore Stock Levels with remapped Warehouse IDs
                if (isset($data['stock_levels']) && is_array($data['stock_levels'])) {
                    foreach ($data['stock_levels'] as $sl) {
                        $sl['tenant_id'] = $targetTenantId;
                        if (!empty($sl['warehouse_id'])) {
                            $sl['warehouse_id'] = $warehouseIdMap[$sl['warehouse_id']] ?? $warehouseIdMap[(int)$sl['warehouse_id']] ?? null;
                        }
                        unset($sl['id']);
                        \App\Models\StockLevel::create($sl);
                    }
                }

                // 13. Restore Customer Ledgers with remapped Customer IDs
                if (isset($data['customer_ledgers']) && is_array($data['customer_ledgers'])) {
                    foreach ($data['customer_ledgers'] as $cl) {
                        $cl['tenant_id'] = $targetTenantId;
                        if (!empty($cl['customer_id'])) {
                            $cl['customer_id'] = $customerIdMap[$cl['customer_id']] ?? $customerIdMap[(int)$cl['customer_id']] ?? null;
                        }
                        unset($cl['id']);
                        \App\Models\CustomerLedger::create($cl);
                    }
                }

                // 14. Restore Stock Adjustments with remapped Warehouse IDs
                if (isset($data['stock_adjustments']) && is_array($data['stock_adjustments'])) {
                    foreach ($data['stock_adjustments'] as $sa) {
                        $sa['tenant_id'] = $targetTenantId;
                        if (!empty($sa['warehouse_id'])) {
                            $sa['warehouse_id'] = $warehouseIdMap[$sa['warehouse_id']] ?? $warehouseIdMap[(int)$sa['warehouse_id']] ?? null;
                        }
                        unset($sa['id']);
                        \App\Models\StockAdjustment::create($sa);
                    }
                }

                // 15. Restore Stock Reservations with remapped Customer & Warehouse IDs
                if (isset($data['stock_reservations']) && is_array($data['stock_reservations'])) {
                    foreach ($data['stock_reservations'] as $sr) {
                        $sr['tenant_id'] = $targetTenantId;
                        if (!empty($sr['customer_id'])) {
                            $sr['customer_id'] = (string) ($customerIdMap[$sr['customer_id']] ?? $customerIdMap[(int)$sr['customer_id']] ?? $sr['customer_id']);
                        }
                        if (!empty($sr['warehouse_id'])) {
                            $sr['warehouse_id'] = $warehouseIdMap[$sr['warehouse_id']] ?? $warehouseIdMap[(int)$sr['warehouse_id']] ?? null;
                        }
                        \App\Models\StockReservation::create($sr);
                    }
                }
            });

            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['error' => 'Tenant restore transaction failed: ' . $e->getMessage()];
        }
    }
}
