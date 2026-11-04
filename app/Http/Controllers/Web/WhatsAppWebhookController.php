<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Payment;
use App\Services\Notification\WhatsAppService;
use App\Console\Commands\SendDailyWhatsAppReportCommand;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Meta Webhook Handshake Verification (GET /api/whatsapp/webhook).
     */
    public function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('whatsapp.webhook_verify_token', 'victorious_pos_secret_token_2026');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('[WhatsApp Webhook] Handshake verified successfully.');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[WhatsApp Webhook] Handshake failed: invalid verify token provided.', [
            'received' => $token,
            'expected' => $expectedToken,
        ]);

        return response('Forbidden: Invalid verification token', 403);
    }

    /**
     * Meta Incoming WhatsApp Message Handler (POST /api/whatsapp/webhook).
     */
    public function handleIncoming(Request $request)
    {
        $payload = $request->all();

        // Check if message object exists in standard Meta webhook payload
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

        if (!$message || ($message['type'] ?? '') !== 'text') {
            return response('EVENT_RECEIVED', 200);
        }

        $rawPhone = $message['from'] ?? '';
        $text = trim($message['text']['body'] ?? '');
        $senderPhone = $this->whatsAppService->formatPhoneNumber($rawPhone);

        if (empty($senderPhone) || empty($text)) {
            return response('EVENT_RECEIVED', 200);
        }

        Log::info("[WhatsApp Webhook] Inbound message from {$senderPhone}: '{$text}'");

        try {
            // ─────────────────────────────────────────────────────────────
            // 1. VERIFICATION COMMAND CHECK (VERIFY <code> or #<code>)
            // ─────────────────────────────────────────────────────────────
            if (preg_match('/^(?:verify|#)\s*([a-zA-Z0-9]{4,10})$/i', $text, $matches)) {
                $code = strtoupper(trim($matches[1]));
                return $this->handleWorkerVerification($senderPhone, $code);
            }

            // ─────────────────────────────────────────────────────────────
            // 2. AUTHORIZATION & ROLE SCOPING CHECK
            // ─────────────────────────────────────────────────────────────
            // Look up verified user by whatsapp_phone
            $user = User::withoutGlobalScopes()
                ->where('whatsapp_phone', $senderPhone)
                ->whereNotNull('whatsapp_verified_at')
                ->where('disabled', false)
                ->first();

            // Look up tenant setting for fallback owner phone
            $ownerSetting = Setting::withoutGlobalScopes()
                ->where('owner_whatsapp_phone', $senderPhone)
                ->first();

            if (!$user && !$ownerSetting) {
                $deniedMsg = "🔒 *Access Denied — Victorious Market POS*\n\n"
                    . "Your phone number (+{$senderPhone}) is not registered as an authorized worker or owner.\n\n"
                    . "Please contact your store administrator to link your WhatsApp under Staff & Roles in the POS interface.\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n"
                    . "Powered by Victorious Market\n"
                    . "Your Trusted Online Market";
                $this->whatsAppService->sendMessage($senderPhone, $deniedMsg);
                return response('EVENT_RECEIVED', 200);
            }

            // Determine tenant context
            $tenantId = $user ? $user->tenant_id : ($ownerSetting->tenant_id ?? 'default-tenant');
            $tenantName = config('app.name', 'Victorious Market');
            $setting = $ownerSetting ?: (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first() ?: Setting::first());

            // Determine warehouse/branch scope
            $isExecutive = $user ? ($user->isAdmin() || ($user->isExecutive() && empty($user->warehouse_id))) : true;
            $userWarehouseId = ($user && !$isExecutive) ? $user->warehouse_id : null;

            // ─────────────────────────────────────────────────────────────
            // 3. COMMAND DISPATCHER (ZERO AI, PURE DETERMINISTIC SPEED)
            // ─────────────────────────────────────────────────────────────
            $cleanText = strtoupper($text);
            $parts = preg_split('/\s+/', $text, 2);
            $firstWord = strtoupper($parts[0] ?? '');
            $argument = trim($parts[1] ?? '');

            switch ($firstWord) {
                case 'REPORT':
                case 'CLOSE':
                    $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                    break;

                case 'SALES':
                    $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case 'CASH':
                    $this->dispatchCashDrawer($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case 'LOW':
                    $this->dispatchLowStockAlert($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case 'STOCK':
                    $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $argument);
                    break;

                case 'MENU':
                case 'HELP':
                default:
                    $this->dispatchHelpMenu($senderPhone, $user, $ownerSetting, $userWarehouseId, $isExecutive);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error("[WhatsApp Webhook Error] {$e->getMessage()}", [
                'sender' => $senderPhone,
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = "⚠️ *Victorious POS Alert*\n\n"
                . "An error occurred while compiling your requested branch summary. Please try again in a moment or contact support.";
            $this->whatsAppService->sendMessage($senderPhone, $errorMsg);
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Handle 1-Tap Worker WhatsApp Phone Linking.
     */
    protected function handleWorkerVerification(string $senderPhone, string $code): Response
    {
        $user = User::withoutGlobalScopes()
            ->where('whatsapp_verify_token', $code)
            ->where('whatsapp_verify_expires_at', '>=', now())
            ->first();

        if (!$user) {
            $msg = "⚠️ *Verification Failed — Victorious Market POS*\n\n"
                . "The verification code *{$code}* is invalid or has expired.\n\n"
                . "Please click 'Link WhatsApp' in the POS Staff Management screen to generate a fresh verification link.\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "Powered by Victorious Market\n"
                . "Your Trusted Online Market";
            $this->whatsAppService->sendMessage($senderPhone, $msg);
            return response('EVENT_RECEIVED', 200);
        }

        // Successfully link phone to worker profile
        $user->whatsapp_phone = $senderPhone;
        $user->whatsapp_verified_at = now();
        $user->whatsapp_verify_token = null;
        $user->whatsapp_verify_expires_at = null;
        $user->save();

        $branchName = 'Enterprise Headquarters (All Branches)';
        if ($user->warehouse_id) {
            $wh = Warehouse::withoutGlobalScopes()->find($user->warehouse_id);
            if ($wh) $branchName = $wh->name;
        }

        $welcomeMsg = "✅ *Welcome to Victorious Market POS!*\n\n"
            . "👤 Worker: *{$user->name}*\n"
            . "🏷️ Role: *" . strtoupper($user->role) . "*\n"
            . "🏬 Assigned Store: *{$branchName}*\n"
            . "-------------------------------------\n"
            . "Your WhatsApp is now verified to receive real-time on-demand store reports at *₦0.00*.\n\n"
            . "Reply *MENU* anytime to view your authorized commands.\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "Powered by Victorious Market\n"
            . "Your Trusted Online Market";

        $this->whatsAppService->sendMessage($senderPhone, $welcomeMsg);

        Log::info("[WhatsApp Verification] User {$user->name} ({$user->id}) verified with phone {$senderPhone}.");

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Dispatch Full Closing Reconciliation Report (On-Demand).
     */
    protected function dispatchOnDemandReport(string $phone, string $tenantId, string $tenantName, Setting $setting, ?int $userWarehouseId, bool $isExecutive, string $branchArg): void
    {
        $targetWarehouseId = $userWarehouseId;

        // If executive specified a branch number (e.g. REPORT 2), lookup warehouse
        if ($isExecutive && !empty($branchArg)) {
            $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($branchArg);
            if ($wh) {
                $targetWarehouseId = $wh->id;
            }
        }

        $command = app(SendDailyWhatsAppReportCommand::class);
        $data = $command->compileDailyMetrics($tenantId, Carbon::today(), $setting, $tenantName, $targetWarehouseId);
        $message = $this->whatsAppService->buildDailyReportMessage($data);

        $this->whatsAppService->sendMessage($phone, $message);
    }

    /**
     * Dispatch Midday Sales Pulse.
     */
    protected function dispatchSalesPulse(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $branchArg): void
    {
        $targetWarehouseId = $userWarehouseId;
        if ($isExecutive && !empty($branchArg)) {
            $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($branchArg);
            if ($wh) $targetWarehouseId = $wh->id;
        }

        $today = Carbon::today();
        $query = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $today);

        if ($targetWarehouseId) {
            $query->where('warehouse_id', $targetWarehouseId);
        }

        $sales = $query->get();
        $totalSales = $sales->sum('total_amount');
        $count = $sales->count();
        $paidNow = $sales->where('payment_status', 'PAID')->sum('total_amount');
        $debt = $sales->whereIn('payment_status', ['PARTIALLY_PAID', 'UNPAID'])->sum('balance_due');

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "⚡ *Live Sales Pulse — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $today->format('D, d M Y') . " (" . now()->format('h:i A') . ")*\n"
            . "-------------------------------------\n"
            . "💰 Gross Sales Today: *₦" . number_format($totalSales, 0) . "*\n"
            . "🧾 Total Completed Sales: *{$count} transactions*\n"
            . "💵 Settled Immediately: *₦" . number_format($paidNow, 0) . "*\n"
            . "⏳ Customer Credit / Balance Due: *₦" . number_format($debt, 0) . "*\n\n"
            . "Reply *REPORT* for full closing audit or *CASH* for drawer balance.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
    }

    /**
     * Dispatch Cash in Drawer Balance.
     */
    protected function dispatchCashDrawer(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $branchArg): void
    {
        $targetWarehouseId = $userWarehouseId;
        if ($isExecutive && !empty($branchArg)) {
            $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($branchArg);
            if ($wh) $targetWarehouseId = $wh->id;
        }

        $today = Carbon::today();
        $query = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('method', 'cash')
            ->whereDate('created_at', $today);

        if ($targetWarehouseId) {
            $query->whereHas('sale', function ($q) use ($targetWarehouseId) {
                $q->where('warehouse_id', $targetWarehouseId);
            });
        }

        $cashCollected = $query->sum('amount');

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "💵 *Cash Drawer Audit — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "🕒 Time: *" . now()->format('h:i A') . "*\n"
            . "-------------------------------------\n"
            . "💰 Expected Cash in Drawer: *₦" . number_format($cashCollected, 0) . "*\n\n"
            . "⚠️ *Auditor Note:* Verify physical currency against this number before handover or bank deposit.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
    }

    /**
     * Dispatch Low Stock Alert Items (<= 5).
     */
    protected function dispatchLowStockAlert(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $branchArg): void
    {
        $targetWarehouseId = $userWarehouseId;
        if ($isExecutive && !empty($branchArg)) {
            $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($branchArg);
            if ($wh) $targetWarehouseId = $wh->id;
        }

        $query = StockLevel::withoutGlobalScopes()
            ->with('product')
            ->where('tenant_id', $tenantId)
            ->where('physical_stock', '<=', 5);

        if ($targetWarehouseId) {
            $query->where('warehouse_id', $targetWarehouseId);
        }

        $lowStock = $query->limit(10)->get();

        $branchLabel = 'All Branches';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        if ($lowStock->isEmpty()) {
            $msg = "🟢 *Stock Health Clean — Victorious Market POS*\n"
                . "🏬 Branch: *{$branchLabel}*\n"
                . "All inventory items are currently above safety thresholds (> 5 units). No immediate reorders needed.";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
            return;
        }

        $itemsText = "";
        $i = 1;
        foreach ($lowStock as $stock) {
            $pName = $stock->product->name ?? 'Product #' . $stock->product_id;
            $itemsText .= "{$i}. *{$pName}*\n   • Physical On Hand: *{$stock->physical_stock} units*\n   • Price: *₦" . number_format($stock->product->unitPrice ?? 0, 0) . "*\n";
            $i++;
        }

        $msg = "⚠️ *Low Stock Radar — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "The following items require immediate restock / purchase order:\n\n"
            . $itemsText . "\n"
            . "Reply *STOCK <name>* to check any other specific product.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
    }

    /**
     * Instant Stock & Price Lookup for Attendants / Managers.
     */
    protected function dispatchStockLookup(string $phone, string $tenantId, ?int $userWarehouseId, string $queryStr): void
    {
        if (empty($queryStr)) {
            $msg = "ℹ️ *How to check stock:*\nReply *STOCK <product name>*\nExample: `STOCK milk` or `STOCK cement`";
            $this->whatsAppService->sendMessage($phone, $msg);
            return;
        }

        $products = Product::withoutGlobalScopes()
            ->where(function ($q) use ($queryStr) {
                $q->where('name', 'like', "%{$queryStr}%")
                  ->orWhere('category', 'like', "%{$queryStr}%");
            })
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            $msg = "🔍 No products found matching: *\"{$queryStr}\"*\nPlease verify spelling and try again.";
            $this->whatsAppService->sendMessage($phone, $msg);
            return;
        }

        $results = "";
        $i = 1;
        foreach ($products as $p) {
            $stockQuery = StockLevel::withoutGlobalScopes()
                ->where('product_id', $p->id);

            if ($userWarehouseId) {
                $stockQuery->where('warehouse_id', $userWarehouseId);
            }

            $physical = (int) $stockQuery->sum('physical_stock');
            $allocated = (int) $stockQuery->sum('allocated_stock');
            $available = max(0, $physical - $allocated);

            $statusEmoji = $available > 5 ? '🟢' : ($available > 0 ? '🟡' : '🔴');

            $results .= "{$i}. *{$p->name}*\n"
                . "   • Unit Price: *₦" . number_format($p->unitPrice ?? 0, 0) . "*\n"
                . "   • Physical on Hand: *{$physical}*\n"
                . "   • Allocated/Unsupplied: *{$allocated}*\n"
                . "   • Available for Sale: {$statusEmoji} *{$available} units*\n\n";
            $i++;
        }

        $msg = "📦 *Inventory Search for \"{$queryStr}\":*\n\n" . $results . "Reply *REPORT* for daily reconciliation.";
        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
    }

    /**
     * Dispatch Help / Command Menu.
     */
    protected function dispatchHelpMenu(string $phone, ?User $user, ?Setting $ownerSetting, ?int $userWarehouseId, bool $isExecutive): void
    {
        $roleName = $user ? strtoupper($user->role) : 'EXECUTIVE STORE OWNER';
        $branchName = 'Consolidated (All Branches)';
        if ($userWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($userWarehouseId);
            if ($wh) $branchName = $wh->name;
        }

        $menu = "🤖 *Victorious Market POS — Command Console*\n"
            . "👤 Role: *{$roleName}*\n"
            . "🏬 Scope: *{$branchName}*\n"
            . "-------------------------------------\n"
            . "Reply with any keyword to get real-time data at *₦0.00*:\n\n"
            . "• *REPORT* — Full daily closing reconciliation\n"
            . "• *SALES*  — Live sales total so far today\n"
            . "• *CASH*   — Expected physical cash in drawer\n"
            . "• *LOW*    — Items running low (≤ 5 units)\n"
            . "• *STOCK <item>* — Instant price & quantity lookup\n";

        if ($isExecutive) {
            $menu .= "• *REPORT <branch_id>* — Specific branch audit\n";
        }

        $menu .= "\n_System: Victorious Market POS Engine_";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu));
    }

    /**
     * Append Universal Victorious Market Footer.
     */
    protected function appendFooter(string $message): string
    {
        return $message
            . "\n━━━━━━━━━━━━━━━━━━━━\n"
            . "Powered by Victorious Market\n"
            . "Your Trusted Online Market";
    }
}

