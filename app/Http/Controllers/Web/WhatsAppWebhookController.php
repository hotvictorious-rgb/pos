<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\CustomerLedger;
use App\Models\Customer;
use App\Models\SalesReturn;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Activity;
use App\Services\Notification\WhatsAppService;
use App\Console\Commands\SendDailyWhatsAppReportCommand;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

        // Extract message object from standard Meta webhook payload
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

        if (!$message) {
            return response('EVENT_RECEIVED', 200);
        }

        // Support standard text, interactive button/list replies, and quick replies
        $rawText = '';
        $messageType = $message['type'] ?? '';

        if ($messageType === 'text') {
            $rawText = $message['text']['body'] ?? '';
        } elseif ($messageType === 'interactive') {
            $rawText = $message['interactive']['button_reply']['title']
                ?? ($message['interactive']['button_reply']['id']
                ?? ($message['interactive']['list_reply']['title']
                ?? ($message['interactive']['list_reply']['id'] ?? '')));
        } elseif ($messageType === 'button') {
            $rawText = $message['button']['text'] ?? ($message['button']['payload'] ?? '');
        }

        $text = trim($rawText);
        $rawPhone = $message['from'] ?? '';
        $senderPhone = $this->whatsAppService->formatPhoneNumber($rawPhone);

        if ($senderPhone === '' || $text === '') {
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
            // 2. AUTHORIZATION & ROLE SCOPING CHECK (RESILIENT PHONE VARIANTS)
            // ─────────────────────────────────────────────────────────────
            $phoneVariants = array_unique(array_filter([
                $senderPhone,
                '+' . $senderPhone,
                str_starts_with($senderPhone, '234') && strlen($senderPhone) === 13 ? '0' . substr($senderPhone, 3) : null,
                str_starts_with($senderPhone, '234') && strlen($senderPhone) === 13 ? substr($senderPhone, 3) : null,
                ltrim($senderPhone, '+'),
            ]));

            // Look up verified user by whatsapp_phone
            $user = User::withoutGlobalScopes()
                ->where(function ($q) use ($phoneVariants) {
                    foreach ($phoneVariants as $pv) {
                        $q->orWhere('whatsapp_phone', $pv);
                    }
                })
                ->whereNotNull('whatsapp_verified_at')
                ->where('disabled', false)
                ->first();

            // Look up tenant setting for fallback owner phone
            $ownerSetting = Setting::withoutGlobalScopes()
                ->where(function ($q) use ($phoneVariants) {
                    foreach ($phoneVariants as $pv) {
                        $q->orWhere('owner_whatsapp_phone', $pv);
                    }
                })
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
            $setting = $ownerSetting ?: (Setting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first() ?: (Setting::withoutGlobalScopes()->first() ?: new Setting()));
            $tenantName = !empty($setting->businessName) ? $setting->businessName : (!empty($setting->store_name) ? $setting->store_name : config('app.name', 'Victorious Market'));

            // Determine warehouse/branch scope
            $isExecutive = $user ? ($user->isAdmin() || ($user->isExecutive() && empty($user->warehouse_id))) : true;
            $userWarehouseId = ($user && !$isExecutive) ? $user->warehouse_id : null;

            // ─────────────────────────────────────────────────────────────
            // 3. INTERACTIVE USSD COMMAND & NAVIGATION ENGINE
            // ─────────────────────────────────────────────────────────────
            $rawInput = trim($text);
            $upperInput = strtoupper($rawInput);

            // Nav session state (15 min per user session)
            $stateKey = "wa_nav_state_{$senderPhone}";
            $currentState = Cache::get($stateKey, 'ROOT');

            // A. Global Reset / Main Menu Navigation
            if ($upperInput === '0' || $upperInput === '00' || in_array($upperInput, ['MENU', 'HELP', 'HI', 'HELLO', 'START', 'COMMANDS', 'INFO', 'MAIN', 'HOME', 'RESET', 'CANCEL'])) {
                Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                return response('EVENT_RECEIVED', 200);
            }

            // B. Direct USSD Shortcode Notation (e.g. 1.1, 1*1, 2.1 Peak Milk, 3.2 Okon)
            if (preg_match('/^(\d+)[\.\*](\d+)(?:\s+(.*))?$/', $rawInput, $matches)) {
                $cat = (int) $matches[1];
                $action = (int) $matches[2];
                $extra = trim($matches[3] ?? '');

                $this->handleUssdDirectAction($cat, $action, $extra, $senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $user, $ownerSetting);
                return response('EVENT_RECEIVED', 200);
            }

            // C. Priority Global Direct Keywords (Always execute directly)
            $parts = preg_split('/\s+/', $rawInput, 2);
            $firstWord = strtoupper(trim($parts[0] ?? ''));
            $argument = trim($parts[1] ?? '');

            switch ($firstWord) {
                case 'REPORT':
                case 'CLOSE':
                case 'CLOSING':
                case 'SUMMARY':
                case 'AUDIT':
                    $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'SALES':
                case 'PULSE':
                case 'TODAY':
                case 'REVENUE':
                    $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'CASH':
                case 'DRAWER':
                case 'BALANCE':
                case 'TILL':
                    $this->dispatchCashDrawer($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'SOLD':
                case 'ITEMS':
                case 'BREAKDOWN':
                case 'PRODUCTS_SOLD':
                    $this->dispatchSoldItems($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'LOW':
                case 'ALERT':
                case 'RESTOCK':
                case 'OUT':
                    $this->dispatchLowStockAlert($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'DEBT':
                case 'DEBTORS':
                case 'CREDIT':
                case 'RECEIVABLES':
                    $this->dispatchCustomerDebtBook($senderPhone, $tenantId, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'CASHIERS':
                case 'SHIFTS':
                case 'STAFF':
                case 'TILLS':
                    $this->dispatchCashierBreakdown($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'TRANSFERS':
                case 'WAYBILLS':
                case 'DISPATCH':
                    $this->dispatchTransferWaybills($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                    return response('EVENT_RECEIVED', 200);

                case 'LOG':
                case 'ACTIVITY':
                case 'TIMELINE':
                case 'HISTORY':
                case 'VOIDS':
                    $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    return response('EVENT_RECEIVED', 200);

                case 'STOCK':
                    if (strtoupper($argument) === 'ALL' || empty($argument)) {
                        if (strtoupper($argument) === 'ALL') {
                            $this->dispatchAllStockSummary($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        } else {
                            $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $argument);
                        }
                    } else {
                        $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $argument);
                    }
                    return response('EVENT_RECEIVED', 200);
            }

            // D. Awaiting Input States (From previous step prompts)
            if ($currentState === 'AWAIT_STOCK_QUERY') {
                Cache::put($stateKey, 'STOCK', now()->addMinutes(30));
                $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $rawInput);
                return response('EVENT_RECEIVED', 200);
            }

            if ($currentState === 'AWAIT_DEBT_QUERY') {
                Cache::put($stateKey, 'DEBT', now()->addMinutes(30));
                $this->dispatchCustomerDebtBook($senderPhone, $tenantId, $rawInput);
                return response('EVENT_RECEIVED', 200);
            }

            // E. Contextual State Number Navigation
            $handled = $this->handleContextualNav($currentState, $firstWord, $argument, $senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $user, $ownerSetting);
            if ($handled) {
                return response('EVENT_RECEIVED', 200);
            }

            // F. Fallback: Smart Product Search or Root Main Menu
            if ($this->hasMatchingProducts($tenantId, $rawInput)) {
                $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $rawInput);
            } else {
                Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
            }
        } catch (\Throwable $e) {
            Log::error("[WhatsApp Webhook Error] {$e->getMessage()}", [
                'sender' => $senderPhone,
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = "⚠️ *Victorious POS Alert*\n\n"
                . "We could not compile your request at this moment. Please reply *MENU* to view authorized commands.";
            $this->whatsAppService->sendMessage($senderPhone, $this->appendFooter($errorMsg));
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Check if text matches products in database.
     */
    protected function hasMatchingProducts(string $tenantId, string $queryStr): bool
    {
        if (empty($queryStr) || strlen($queryStr) < 2) {
            return false;
        }

        return Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('archived', false)
            ->where(function ($q) use ($queryStr) {
                $q->where('name', 'like', "%{$queryStr}%")
                  ->orWhere('code', 'like', "%{$queryStr}%");
            })
            ->exists();
    }

    /**
     * Smart Date & Branch Argument Parser.
     * Supports: 'yesterday', 'today', '2026-11-03', '2', 'Branch 1', 'Main'
     */
    protected function parseDateAndBranch(string $arg, string $tenantId, ?int $defaultWarehouseId, bool $isExecutive): array
    {
        $targetDate = Carbon::today();
        $targetWarehouseId = $defaultWarehouseId;

        if (empty($arg)) {
            return [$targetDate, $targetWarehouseId];
        }

        $tokens = preg_split('/\s+/', trim($arg));
        foreach ($tokens as $token) {
            $lower = strtolower($token);

            // Date keywords
            if ($lower === 'yesterday') {
                $targetDate = Carbon::yesterday();
                continue;
            }
            if ($lower === 'today') {
                $targetDate = Carbon::today();
                continue;
            }

            // Explicit date formats YYYY-MM-DD or DD-MM-YYYY
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $token) || preg_match('/^\d{2}-\d{2}-\d{4}$/', $token) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $token)) {
                try {
                    $targetDate = Carbon::parse($token);
                    continue;
                } catch (\Throwable $e) {}
            }

            // Branch lookup (only if executive with permission)
            if ($isExecutive) {
                if (is_numeric($token)) {
                    $wh = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->find((int) $token);
                    if ($wh) {
                        $targetWarehouseId = $wh->id;
                        continue;
                    }
                }

                $wh = Warehouse::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('name', 'like', "%{$token}%")
                    ->first();
                if ($wh) {
                    $targetWarehouseId = $wh->id;
                    continue;
                }
            }
        }

        return [$targetDate, $targetWarehouseId];
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
     * Dispatch Full Closing Reconciliation Report with Date/Branch Filtering & Fallback Resilience.
     */
    protected function dispatchOnDemandReport(string $phone, string $tenantId, string $tenantName, Setting $setting, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        try {
            $command = app(SendDailyWhatsAppReportCommand::class);
            $data = $command->compileDailyMetrics($tenantId, $targetDate, $setting, $tenantName, $targetWarehouseId);
            $message = $this->whatsAppService->buildDailyReportMessage($data);
            $this->whatsAppService->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::error("[WhatsApp Report Compilation Error] {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            // Resilient Fallback: compile core numbers directly so user is never left without data
            $this->dispatchFallbackCoreAudit($phone, $tenantId, $tenantName, $targetDate, $targetWarehouseId);
        }
    }

    /**
     * Fallback Core Financial Audit (Invoked if deep compiler catches schema or data errors).
     */
    protected function dispatchFallbackCoreAudit(string $phone, string $tenantId, string $tenantName, Carbon $targetDate, ?int $warehouseId): void
    {
        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();
        $dateStartIso = $dateStart->toIso8601String();
        $dateEndIso = $dateEnd->toIso8601String();

        $salesQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
            ->where(function ($q) use ($dateStart, $dateEnd, $dateStartIso, $dateEndIso) {
                $q->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
                  ->orWhereBetween('created_at', [$dateStart, $dateEnd]);
            });

        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $sales = $salesQuery->get();
        $grossSales = (float) $sales->sum('totalAmount');
        $invoices = $sales->count();
        $paidNow = (float) $sales->sum('paidAmount');
        $cashSales = (float) $sales->sum('cashAmount');
        $posSales = (float) $sales->sum('posAmount');
        $transferSales = (float) $sales->sum('transferAmount');

        $debtCreated = 0.0;
        foreach ($sales as $s) {
            $due = (float) $s->totalAmount - (float) $s->paidAmount;
            if ($due > 0.01) $debtCreated += $due;
        }

        $branchLabel = 'All Branches Consolidated';
        if ($warehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "📊 *{$tenantName} — DAILY FINANCIAL AUDIT*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💰 *SALES & REVENUE*\n"
            . "• *Gross Sales:* ₦" . number_format($grossSales, 2) . "\n"
            . "• *Invoices Completed:* {$invoices}\n"
            . "• *Settled Immediately:* ₦" . number_format($paidNow, 2) . "\n"
            . "• *New Customer Credit / Unpaid:* ₦" . number_format($debtCreated, 2) . "\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💵 *TENDER COLLECTION*\n"
            . "• *Cash in Till:* ₦" . number_format($cashSales, 2) . "\n"
            . "• *POS Terminal:* ₦" . number_format($posSales, 2) . "\n"
            . "• *Bank Transfers:* ₦" . number_format($transferSales, 2) . "\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "🏦 *Net Cash in Drawer:* ₦" . number_format($cashSales, 2) . "\n\n"
            . "Reply *SOLD* for item breakdown or *STOCK <item>* to check inventory.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Midday Sales Pulse with Date and Branch Flexibility.
     */
    protected function dispatchSalesPulse(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();
        $dateStartIso = $dateStart->toIso8601String();
        $dateEndIso = $dateEnd->toIso8601String();

        $query = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
            ->where(function ($q) use ($dateStart, $dateEnd, $dateStartIso, $dateEndIso) {
                $q->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
                  ->orWhereBetween('created_at', [$dateStart, $dateEnd]);
            });

        if ($targetWarehouseId) {
            $query->where('warehouse_id', $targetWarehouseId);
        }

        $sales = $query->get();
        $totalSales = (float) $sales->sum('totalAmount');
        $count = $sales->count();
        $paidNow = (float) $sales->sum('paidAmount');
        $debt = 0.0;
        foreach ($sales as $s) {
            $due = (float) $s->totalAmount - (float) $s->paidAmount;
            if ($due > 0.01) {
                $debt += $due;
            }
        }

        // Tenders breakdown
        $cashTotal = (float) $sales->sum('cashAmount');
        $posTotal = (float) $sales->sum('posAmount');
        $transferTotal = (float) $sales->sum('transferAmount');

        $cashPct = $totalSales > 0 ? round(($cashTotal / $totalSales) * 100, 1) : 0;
        $posPct = $totalSales > 0 ? round(($posTotal / $totalSales) * 100, 1) : 0;
        $transferPct = $totalSales > 0 ? round(($transferTotal / $totalSales) * 100, 1) : 0;
        $avgBasket = $count > 0 ? ($totalSales / $count) : 0;

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $timeLabel = $targetDate->isToday() ? " (" . now()->format('h:i A') . ")" : "";

        $msg = "⚡ *Live Sales Pulse — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . "{$timeLabel}*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💰 *Gross Sales:* ₦" . number_format($totalSales, 2) . "\n"
            . "🧾 *Total Invoices:* {$count} completed\n"
            . "🎯 *Average Basket:* ₦" . number_format($avgBasket, 2) . "\n"
            . "💵 *Settled Immediately:* ₦" . number_format($paidNow, 2) . "\n";
        if ($debt > 0.01) {
            $msg .= "⏳ *Customer Credit / Unpaid:* ₦" . number_format($debt, 2) . "\n";
        }
        $msg .= "-----------------------------------------\n"
            . "💳 *Tender Breakdown:*\n"
            . "   • 💵 Cash in Drawer: *₦" . number_format($cashTotal, 2) . "* ({$cashPct}%)\n"
            . "   • 💳 POS Terminal:   *₦" . number_format($posTotal, 2) . "* ({$posPct}%)\n"
            . "   • 🏦 Bank Transfers: *₦" . number_format($transferTotal, 2) . "* ({$transferPct}%)\n\n"
            . "Reply *1.3* for items sold or *1.4* for full closing audit.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *1.3* for Items Sold | *0* for Main Menu"));
    }

    /**
     * Dispatch Detailed Breakdown of Items Sold with Remaining Stock on Ground.
     */
    protected function dispatchSoldItems(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();
        $dateStartIso = $dateStart->toIso8601String();
        $dateEndIso = $dateEnd->toIso8601String();

        $salesQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
            ->where(function ($q) use ($dateStart, $dateEnd, $dateStartIso, $dateEndIso) {
                $q->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
                  ->orWhereBetween('created_at', [$dateStart, $dateEnd]);
            });

        if ($targetWarehouseId) {
            $salesQuery->where('warehouse_id', $targetWarehouseId);
        }

        $saleIds = $salesQuery->pluck('id')->toArray();

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        if (empty($saleIds)) {
            $msg = "📋 *Sold Products Breakdown — Victorious Market POS*\n"
                . "🏬 Branch: *{$branchLabel}*\n"
                . "📅 Date: *" . $targetDate->format('D, d M Y') . "*\n"
                . "-------------------------------------\n"
                . "ℹ️ No completed product sales recorded for this date.\n\n"
                . "Reply *STOCK* to check shelf inventory or *SALES* for today's summary.";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        $soldItems = SaleItem::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->selectRaw('productId, productName, SUM(quantity) as total_qty, SUM(totalPrice) as total_revenue, AVG(unitPrice) as avg_price')
            ->groupBy('productId', 'productName')
            ->orderByDesc('total_qty')
            ->get();

        $totalUnits = (int) $soldItems->sum('total_qty');
        $totalRevenue = (float) $soldItems->sum('total_revenue');
        $uniqueProducts = $soldItems->count();

        $lines = "";
        $i = 1;

        foreach ($soldItems->take(15) as $item) {
            $p = Product::withoutGlobalScopes()->find($item->productId);
            $stockQuery = StockLevel::withoutGlobalScopes()->where('product_id', $item->productId);
            if ($targetWarehouseId) {
                $stockQuery->where('warehouse_id', $targetWarehouseId);
            }

            $physical = (int) $stockQuery->sum('physical_stock');
            $allocated = (int) $stockQuery->sum('allocated_stock');
            if ($physical === 0 && $stockQuery->count() === 0 && $p) {
                $physical = (int) ($p->currentStock ?? 0);
            }

            $remaining = max(0, $physical - $allocated);
            $remainingBadge = $remaining > 5 ? "🟢 {$remaining} remaining" : ($remaining > 0 ? "🟡 {$remaining} left (LOW)" : "🔴 OUT OF STOCK");

            $pName = $item->productName ?: ($p?->name ?? 'Product #' . $item->productId);
            $lines .= "{$i}️⃣ *{$pName}*\n"
                . "   • Sold: *{$item->total_qty} units* (₦" . number_format($item->total_revenue, 0) . ")\n"
                . "   • Current Stock: *{$remainingBadge}*\n";
            $i++;
        }

        if ($soldItems->count() > 15) {
            $remainingCount = $soldItems->count() - 15;
            $lines .= "\n_...and {$remainingCount} more products sold._\n";
        }

        $msg = "📋 *Sold Items Breakdown — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . "*\n"
            . "-------------------------------------\n"
            . "📦 *Distinct Products Sold:* {$uniqueProducts}\n"
            . "🔢 *Total Physical Units Sold:* {$totalUnits} units\n"
            . "💰 *Total Sold Items Revenue:* ₦" . number_format($totalRevenue, 2) . "\n"
            . "-------------------------------------\n"
            . "📊 *Items Sold & Remaining Shelf Stock:*\n\n"
            . $lines . "\n"
            . "Reply *STOCK <name>* for deep item details or *CASH* for drawer cash.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Complete Inventory Snapshot across All Products.
     */
    protected function dispatchAllStockSummary(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive): void
    {
        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('archived', false)
            ->get();

        $totalProducts = $products->count();
        $totalUnits = 0;
        $totalRetailValue = 0.0;
        $outOfStockCount = 0;
        $lowStockCount = 0;
        $inStockCount = 0;

        $itemsList = [];

        foreach ($products as $p) {
            $stockQuery = StockLevel::withoutGlobalScopes()->where('product_id', $p->id);
            if ($userWarehouseId) {
                $stockQuery->where('warehouse_id', $userWarehouseId);
            }

            $physical = (int) $stockQuery->sum('physical_stock');
            $allocated = (int) $stockQuery->sum('allocated_stock');
            if ($physical === 0 && $stockQuery->count() === 0) {
                $physical = (int) ($p->currentStock ?? 0);
            }

            $available = max(0, $physical - $allocated);
            $totalUnits += $available;
            $totalRetailValue += ($available * (float) ($p->unitPrice ?? 0));

            if ($available <= 0) {
                $outOfStockCount++;
            } elseif ($available <= 5) {
                $lowStockCount++;
            } else {
                $inStockCount++;
            }

            $itemsList[] = [
                'name' => $p->name,
                'available' => $available,
                'price' => (float) ($p->unitPrice ?? 0),
            ];
        }

        // Sort items by available stock descending
        usort($itemsList, fn($a, $b) => $b['available'] <=> $a['available']);

        $topItems = array_slice($itemsList, 0, 10);
        $topListText = "";
        $i = 1;
        foreach ($topItems as $item) {
            $badge = $item['available'] > 5 ? '🟢' : ($item['available'] > 0 ? '🟡' : '🔴');
            $topListText .= "{$i}. {$badge} *{$item['name']}*: {$item['available']} units (₦" . number_format($item['price'], 0) . ")\n";
            $i++;
        }

        $branchLabel = 'All Branches Consolidated';
        if ($userWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($userWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "🏢 *Complete Inventory Snapshot — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "🕒 Time: *" . now()->format('D, d M Y (h:i A)') . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📦 *Total Catalog Items:* {$totalProducts} products\n"
            . "🔢 *Total Physical Units on Ground:* " . number_format($totalUnits) . " units\n"
            . "💰 *Estimated Retail Value:* ₦" . number_format($totalRetailValue, 2) . "\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "🟢 In Healthy Stock: *{$inStockCount} items*\n"
            . "🟡 Low Stock (≤ 5 units): *{$lowStockCount} items*\n"
            . "🔴 Out of Stock: *{$outOfStockCount} items*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📋 *Top Inventory Holdings:*\n"
            . $topListText . "\n"
            . "Reply *STOCK <name>* for single item lookup or *SOLD* for sales breakdown.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Cash in Drawer Balance Reconciled with Closing Engine.
     */
    protected function dispatchCashDrawer(string $phone, string $tenantId, string $tenantName, Setting $setting, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();
        $dateStartIso = $dateStart->toIso8601String();
        $dateEndIso = $dateEnd->toIso8601String();

        // 1. Cash sales today
        $salesQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
            ->where(function ($q) use ($dateStart, $dateEnd, $dateStartIso, $dateEndIso) {
                $q->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
                  ->orWhereBetween('created_at', [$dateStart, $dateEnd]);
            });

        if ($targetWarehouseId) {
            $salesQuery->where('warehouse_id', $targetWarehouseId);
        }

        $cashSales = (float) $salesQuery->sum('cashAmount');

        // 2. Debt recoveries in cash
        $debtRecoveryQuery = CustomerLedger::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->where('type', 'PAYMENT')
            ->where('notes', 'not like', '[REVERSED%');

        if ($targetWarehouseId) {
            $debtRecoveryQuery->where('warehouse_id', $targetWarehouseId);
        }
        $cashRecoveries = abs((float) $debtRecoveryQuery->sum('amount'));

        // 3. Cash refunds
        $returnsQuery = SalesReturn::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd]);

        if ($targetWarehouseId) {
            $returnsQuery->where('warehouse_id', $targetWarehouseId);
        }
        $cashRefunds = (float) $returnsQuery->sum('refundAmount');

        $netCash = max(0.0, round($cashSales + $cashRecoveries - $cashRefunds, 2));

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "💵 *Cash Drawer Audit — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . " (" . now()->format('h:i A') . ")*\n"
            . "-------------------------------------\n"
            . "💰 *Net Expected Cash in Drawer: ₦" . number_format($netCash, 2) . "*\n"
            . "-------------------------------------\n"
            . "   • Cash Sales: *₦" . number_format($cashSales, 2) . "*\n"
            . "   • Debt Recoveries (Cash): *₦" . number_format($cashRecoveries, 2) . "*\n"
            . "   • Cash Refunds/Returns: *-₦" . number_format($cashRefunds, 2) . "*\n\n"
            . "⚠️ *Auditor Note:* Physically count cash in till before shift handover or bank deposit.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Operational Timeline & Audit Trail of Events.
     */
    protected function dispatchOperationalTimeline(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();

        $activities = Activity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->latest('created_at')
            ->take(8)
            ->get();

        $salesCount = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
            ->count();

        $debtRecoveries = CustomerLedger::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->where('type', 'PAYMENT')
            ->where('notes', 'not like', '[REVERSED%')
            ->count();

        $voidsCount = Activity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->whereIn('type', ['TRANSACTION_VOIDED', 'DEBT_PAYMENT_REVERSED', 'STOCK_IN_VOIDED', 'STOCK_OUT_VOIDED'])
            ->count();

        $eventsText = "";
        $i = 1;
        foreach ($activities as $act) {
            $time = Carbon::parse($act->created_at)->format('h:i A');
            $user = $act->userName ?: 'Staff';
            $eventsText .= "{$i}. [{$time}] *{$user}*: " . Str::limit($act->description, 50) . "\n";
            $i++;
        }

        if (empty($eventsText)) {
            $eventsText = "• No security or administrative events logged for this date.\n";
        }

        $branchLabel = 'All Branches Consolidated';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "📜 *Store Operational Audit Trail — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📊 *Activity Summary:*\n"
            . "• Completed Sales: *{$salesCount} transactions*\n"
            . "• Debt Payments Collected: *{$debtRecoveries} payments*\n"
            . "• Administrative Voids/Reversals: *{$voidsCount} items*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "🕒 *Recent Operational Events:*\n\n"
            . $eventsText . "\n"
            . "Reply *REPORT* for closing reconciliation or *SOLD* for itemized sales.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Low Stock Alert Items with Branch Scoping.
     */
    protected function dispatchLowStockAlert(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        $branchLabel = 'All Branches';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        // 1. Try stock_levels table (multi-branch)
        $query = StockLevel::withoutGlobalScopes()
            ->with('product')
            ->where('tenant_id', $tenantId)
            ->where('physical_stock', '<=', 5);

        if ($targetWarehouseId) {
            $query->where('warehouse_id', $targetWarehouseId);
        }

        $lowStock = $query->limit(10)->get();

        // 2. If no stock_levels records, fallback to Product::currentStock
        $itemsText = "";
        $count = 0;

        if ($lowStock->isNotEmpty()) {
            foreach ($lowStock as $stock) {
                if ($stock->product && !$stock->product->archived) {
                    $count++;
                    $pName = $stock->product->name;
                    $itemsText .= "{$count}. *{$pName}*\n   • On Hand: *{$stock->physical_stock} units*\n   • Unit Price: *₦" . number_format($stock->product->unitPrice ?? 0, 0) . "*\n";
                }
            }
        }

        if ($count === 0) {
            $productLow = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('archived', false)
                ->where('currentStock', '<=', 5)
                ->limit(10)
                ->get();

            foreach ($productLow as $prod) {
                $count++;
                $itemsText .= "{$count}. *{$prod->name}*\n   • On Hand: *{$prod->currentStock} units*\n   • Unit Price: *₦" . number_format($prod->unitPrice ?? 0, 0) . "*\n";
            }
        }

        if ($count === 0) {
            $msg = "🟢 *Stock Health Clean — Victorious Market POS*\n"
                . "🏬 Branch: *{$branchLabel}*\n"
                . "All inventory items are currently above safety thresholds (> 5 units). No immediate reorders needed.";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        $msg = "⚠️ *Low Stock Radar — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "The following items require immediate restock / purchase order:\n\n"
            . $itemsText . "\n"
            . "Reply *STOCK <name>* to check any other specific product.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Comprehensive Product & Inventory Intelligence Lookup.
     * Displays: Price, physical stock, units sold today, allocated orders, net available, branch breakdown, category, barcode.
     */
    protected function dispatchStockLookup(string $phone, string $tenantId, ?int $userWarehouseId, string $queryStr): void
    {
        if (empty($queryStr)) {
            $msg = "ℹ️ *How to check stock & prices:*\nReply *STOCK <product name or barcode>*\nExample: `STOCK milk` or `STOCK 10023`\n\n💡 *Tip:* You can also simply type the item name directly!";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('archived', false)
            ->where(function ($q) use ($queryStr) {
                $q->where('name', 'like', "%{$queryStr}%")
                  ->orWhere('code', 'like', "%{$queryStr}%")
                  ->orWhere('brand', 'like', "%{$queryStr}%")
                  ->orWhere('category', 'like', "%{$queryStr}%");
            })
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            $msg = "🔍 *No products found matching:* \"{$queryStr}\"\n\n"
                . "• Please verify spelling or barcode and try again.\n"
                . "• Reply *SOLD* to see what sold today or *LOW* for restock alerts.";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        // Retrieve active branches for multi-branch breakdown
        $branches = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $results = "";
        $i = 1;

        $today = Carbon::today();
        $dateStartIso = $today->copy()->startOfDay()->toIso8601String();
        $dateEndIso = $today->copy()->endOfDay()->toIso8601String();

        foreach ($products as $p) {
            $stockQuery = StockLevel::withoutGlobalScopes()
                ->where('product_id', $p->id);

            if ($userWarehouseId) {
                $stockQuery->where('warehouse_id', $userWarehouseId);
            }

            $physical = (int) $stockQuery->sum('physical_stock');
            $allocated = (int) $stockQuery->sum('allocated_stock');

            // Fallback to Product.currentStock if no warehouse stock level row exists
            if ($physical === 0 && $stockQuery->count() === 0) {
                $physical = (int) ($p->currentStock ?? 0);
            }

            $available = max(0, $physical - $allocated);
            $statusEmoji = $available > 5 ? '🟢' : ($available > 0 ? '🟡' : '🔴');
            $statusText = $available > 5 ? 'IN STOCK' : ($available > 0 ? 'LOW STOCK' : 'OUT OF STOCK');

            // Units sold today of this specific product
            $soldToday = (int) SaleItem::withoutGlobalScopes()
                ->where('productId', $p->id)
                ->whereHas('sale', function ($sq) use ($today, $dateStartIso, $dateEndIso, $userWarehouseId) {
                    $sq->whereNotIn('status', ['VOIDED', 'CANCELLED'])
                       ->where(function ($q) use ($today, $dateStartIso, $dateEndIso) {
                           $q->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
                             ->orWhereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()]);
                       });
                    if ($userWarehouseId) {
                        $sq->where('warehouse_id', $userWarehouseId);
                    }
                })
                ->sum('quantity');

            $results .= "{$i}️⃣ *{$p->name}*\n"
                . "   • 💰 Unit Selling Price: *₦" . number_format($p->unitPrice ?? 0, 2) . "*\n"
                . "   • 📦 Net Available for Sale: {$statusEmoji} *{$available} units* ({$statusText})\n"
                . "   • 🏬 Physical on Ground: *{$physical} units*\n";

            if ($soldToday > 0) {
                $results .= "   • ⚡ Sold Today: *{$soldToday} units sold across trade today*\n";
            }

            if ($allocated > 0) {
                $results .= "   • ⏳ Reserved for Orders: *{$allocated} units*\n";
            }

            if (!empty($p->code)) {
                $results .= "   • 🏷️ Barcode/SKU: `{$p->code}`\n";
            }
            if (!empty($p->category)) {
                $results .= "   • 📂 Category: {$p->category}\n";
            }

            // Per-Branch Breakdown (if multi-branch and user has consolidated view)
            if (empty($userWarehouseId) && $branches->count() > 1) {
                $branchLines = [];
                foreach ($branches as $br) {
                    $brStock = StockLevel::withoutGlobalScopes()
                        ->where('product_id', $p->id)
                        ->where('warehouse_id', $br->id)
                        ->first();

                    if ($brStock && $brStock->physical_stock > 0) {
                        $brAvail = max(0, $brStock->physical_stock - $brStock->allocated_stock);
                        $branchLines[] = "     ↳ {$br->name}: *{$brAvail} avail*";
                    }
                }
                if (!empty($branchLines)) {
                    $results .= "   • 🏢 *Branch Breakdown:*\n" . implode("\n", $branchLines) . "\n";
                }
            }

            $results .= "\n";
            $i++;
        }

        $msg = "📦 *Product Intelligence Search: \"{$queryStr}\"*\n━━━━━━━━━━━━━━━━━━━━\n\n" . $results . "Reply *SOLD* for itemized sales breakdown or *REPORT* for closing audit.";
        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Help / Command Menu (Delegates to Root Main Menu).
     */
    protected function dispatchHelpMenu(string $phone, ?User $user, ?Setting $ownerSetting, ?int $userWarehouseId, bool $isExecutive): void
    {
        Cache::put("wa_nav_state_{$phone}", 'ROOT', now()->addMinutes(30));
        $this->dispatchRootMainMenu($phone, $user, $userWarehouseId, $isExecutive);
    }

    /**
     * Dispatch Root Interactive USSD Console.
     */
    protected function dispatchRootMainMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $roleName = $user ? strtoupper($user->role) : 'EXECUTIVE STORE OWNER';
        $branchName = 'Consolidated (All Branches)';
        if ($userWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($userWarehouseId);
            if ($wh) $branchName = $wh->name;
        }

        $nowStr = now()->format('D, d M Y | h:i A');

        $menu = "🏛️ *VICTORIOUS MARKET POS — USSD CONSOLE*\n"
            . "👤 User: *{$roleName}* | 🏬 Scope: *{$branchName}*\n"
            . "🕒 *{$nowStr}*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select a service by replying with a number:\n\n"
            . "1️⃣ 📊 *Sales & Revenue*\n"
            . "   ↳ Pulse, Cash Drawer, Items Sold\n\n"
            . "2️⃣ 📦 *Inventory & Stock*\n"
            . "   ↳ Unit Balances, Valuation, Low Stock\n\n"
            . "3️⃣ 💳 *Debt & Receivables*\n"
            . "   ↳ Customer Debt Book, Debtor Profiles\n\n"
            . "4️⃣ 💼 *Cashiers & Tills*\n"
            . "   ↳ Shift Scoreboard, Sales per Till\n\n"
            . "5️⃣ 🚚 *Logistics & Waybills*\n"
            . "   ↳ Active Transfers & Cargo in Transit\n\n"
            . "6️⃣ 🛡️ *Audit & Security*\n"
            . "   ↳ Operational Timeline, Voids, Logs\n\n"
            . "7️⃣ 📋 *Full Closing Audit (REPORT)*\n"
            . "   ↳ Complete Financial Closing Reconciliation\n\n"
            . "0️⃣ 🔄 *Reset / Refresh*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Fast USSD Shortcuts:* Reply *1.1*, *1.2*, *2.2*, *3.1*, etc.\n"
            . "💡 *Direct Product Search:* Reply with any item name (e.g. *Milk*) anytime!";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply with a number (1-7) or send *0* anytime to return here."));
    }

    /**
     * Dispatch Sub-Menu 1: Sales & Revenue.
     */
    protected function dispatchSalesSubMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $menu = "📊 *SALES & REVENUE MENU — VICTORIOUS POS*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select an option by replying with a number:\n\n"
            . "1️⃣ *Today's Sales Pulse* — Gross volume, ticket count & tenders\n"
            . "2️⃣ *Cash Drawer Audit* — Till physical currency to bank\n"
            . "3️⃣ *Items Sold Breakdown* — Units sold & remaining shelf stock\n"
            . "4️⃣ *Full Closing Audit* — Complete financial reconciliation\n"
            . "5️⃣ *Yesterday's Sales* — Previous day revenue summary\n\n"
            . "0️⃣ 🔙 *Back to Main Menu*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Fast Shortcut:* Jump directly with *1.1*, *1.2*, *1.3*, *1.4*.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply *1*, *2*, *3*, *4*, *5* or *0* for Main Menu."));
    }

    /**
     * Dispatch Sub-Menu 2: Inventory & Stock.
     */
    protected function dispatchStockSubMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $menu = "📦 *INVENTORY & STOCK MENU — VICTORIOUS POS*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select an option by replying with a number:\n\n"
            . "1️⃣ *Check Product Stock* — Physical units, location & prices\n"
            . "   ↳ *Reply `2.1 <item>` (e.g. `2.1 Milk`)*\n"
            . "2️⃣ *Store Inventory Valuation* — Total units & retail value\n"
            . "3️⃣ *Low Stock Restock Radar* — Products at or below safety level\n"
            . "4️⃣ *Inter-Branch Waybills* — Cargo currently in road transit\n\n"
            . "0️⃣ 🔙 *Back to Main Menu*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Tip:* You can type any product name or barcode directly anytime!";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply *1*, *2*, *3*, *4* or *0* for Main Menu."));
    }

    /**
     * Dispatch Sub-Menu 3: Debt & Receivables.
     */
    protected function dispatchDebtSubMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $menu = "💳 *DEBT & RECEIVABLES MENU — VICTORIOUS POS*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select an option by replying with a number:\n\n"
            . "1️⃣ *Debt Book Overview* — Total store receivables & top debtors\n"
            . "2️⃣ *Lookup Customer Profile* — Search individual customer balance\n"
            . "   ↳ *Reply `3.2 <name>` (e.g. `3.2 Okon`)*\n"
            . "3️⃣ *Debts Recovered Today* — Debt collections received today\n\n"
            . "0️⃣ 🔙 *Back to Main Menu*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Tip:* Send `DEBT <name>` anytime for instant single customer lookup.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply *1*, *2*, *3* or *0* for Main Menu."));
    }

    /**
     * Dispatch Sub-Menu 4: Cashiers & Tills.
     */
    protected function dispatchCashiersSubMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $menu = "💼 *CASHIERS & TILLS MENU — VICTORIOUS POS*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select an option by replying with a number:\n\n"
            . "1️⃣ *Today's Cashier Scoreboard* — Invoices completed & sales per till\n"
            . "2️⃣ *Yesterday's Cashier Scoreboard* — Previous day staff tally\n\n"
            . "0️⃣ 🔙 *Back to Main Menu*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Fast Shortcut:* Reply *4.1* or *4.2* directly.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply *1*, *2* or *0* for Main Menu."));
    }

    /**
     * Dispatch Sub-Menu 5: Logistics & Waybills.
     */
    protected function dispatchLogisticsSubMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $menu = "🚚 *LOGISTICS & WAYBILLS MENU — VICTORIOUS POS*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select an option by replying with a number:\n\n"
            . "1️⃣ *Active In-Transit Cargo* — Waybills on road between warehouses\n"
            . "2️⃣ *All Recent Dispatches* — Completed transfer movements\n\n"
            . "0️⃣ 🔙 *Back to Main Menu*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Fast Shortcut:* Reply *5.1* directly.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply *1* or *0* for Main Menu."));
    }

    /**
     * Dispatch Sub-Menu 6: Audit Log & Security.
     */
    protected function dispatchAuditSubMenu(string $phone, ?User $user, ?int $userWarehouseId, bool $isExecutive): void
    {
        $menu = "🛡️ *AUDIT LOG & SECURITY MENU — VICTORIOUS POS*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "Select an option by replying with a number:\n\n"
            . "1️⃣ *Today's Operational Timeline* — Sales, voids, returns & system actions\n"
            . "2️⃣ *Yesterday's Timeline* — Activity log for yesterday\n"
            . "3️⃣ *Date-Specific Timeline* — Reply `6.3 YYYY-MM-DD`\n\n"
            . "0️⃣ 🔙 *Back to Main Menu*\n"
            . "━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 *Fast Shortcut:* Reply *6.1* or *6.2* directly.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($menu, "Reply *1*, *2* or *0* for Main Menu."));
    }

    /**
     * Handle Contextual USSD Menu Tree Navigation based on Current Session State.
     */
    protected function handleContextualNav(
        string $currentState,
        string $choice,
        string $argument,
        string $senderPhone,
        string $tenantId,
        string $tenantName,
        Setting $setting,
        ?int $userWarehouseId,
        bool $isExecutive,
        ?User $user,
        ?Setting $ownerSetting
    ): bool {
        $stateKey = "wa_nav_state_{$senderPhone}";

        switch ($currentState) {
            case 'ROOT':
                switch ($choice) {
                    case '1':
                        Cache::put($stateKey, 'SALES', now()->addMinutes(30));
                        $this->dispatchSalesSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                    case '2':
                        Cache::put($stateKey, 'STOCK', now()->addMinutes(30));
                        $this->dispatchStockSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                    case '3':
                        Cache::put($stateKey, 'DEBT', now()->addMinutes(30));
                        $this->dispatchDebtSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                    case '4':
                        Cache::put($stateKey, 'CASHIERS', now()->addMinutes(30));
                        $this->dispatchCashiersSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                    case '5':
                        Cache::put($stateKey, 'LOGISTICS', now()->addMinutes(30));
                        $this->dispatchLogisticsSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                    case '6':
                        Cache::put($stateKey, 'AUDIT', now()->addMinutes(30));
                        $this->dispatchAuditSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                    case '7':
                        $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    // Backward-compatible direct numbers from old menu:
                    case '8':
                        $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '9':
                        $this->dispatchCustomerDebtBook($senderPhone, $tenantId, $argument);
                        return true;
                    case '10':
                        $this->dispatchCashierBreakdown($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '11':
                        $this->dispatchTransferWaybills($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;

            case 'SALES':
                switch ($choice) {
                    case '1':
                        $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '2':
                        $this->dispatchCashDrawer($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '3':
                        $this->dispatchSoldItems($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '4':
                        $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '5':
                        $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, 'yesterday');
                        return true;
                    case '0':
                        Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                        $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;

            case 'STOCK':
                switch ($choice) {
                    case '1':
                        if (!empty($argument)) {
                            $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $argument);
                        } else {
                            Cache::put($stateKey, 'AWAIT_STOCK_QUERY', now()->addMinutes(15));
                            $msg = "🔍 *Check Product Stock*\n\nPlease reply with the product name or barcode (e.g. *Peak Milk* or *Indomie*).";
                            $this->whatsAppService->sendMessage($senderPhone, $this->appendFooter($msg, "Reply with item name, or *0* to cancel"));
                        }
                        return true;
                    case '2':
                        $this->dispatchAllStockSummary($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        return true;
                    case '3':
                        $this->dispatchLowStockAlert($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '4':
                        $this->dispatchTransferWaybills($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        return true;
                    case '0':
                        Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                        $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;

            case 'DEBT':
                switch ($choice) {
                    case '1':
                        $this->dispatchCustomerDebtBook($senderPhone, $tenantId, '');
                        return true;
                    case '2':
                        if (!empty($argument)) {
                            $this->dispatchCustomerDebtBook($senderPhone, $tenantId, $argument);
                        } else {
                            Cache::put($stateKey, 'AWAIT_DEBT_QUERY', now()->addMinutes(15));
                            $msg = "👤 *Lookup Customer Debt Profile*\n\nPlease reply with the customer name, phone number, or code (e.g. *Okon* or *08012345678*).";
                            $this->whatsAppService->sendMessage($senderPhone, $this->appendFooter($msg, "Reply with customer name, or *0* to cancel"));
                        }
                        return true;
                    case '3':
                        $this->dispatchCustomerDebtBook($senderPhone, $tenantId, 'today');
                        return true;
                    case '0':
                        Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                        $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;

            case 'CASHIERS':
                switch ($choice) {
                    case '1':
                        $this->dispatchCashierBreakdown($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '2':
                        $this->dispatchCashierBreakdown($senderPhone, $tenantId, $userWarehouseId, $isExecutive, 'yesterday');
                        return true;
                    case '0':
                        Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                        $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;

            case 'LOGISTICS':
                switch ($choice) {
                    case '1':
                        $this->dispatchTransferWaybills($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        return true;
                    case '0':
                        Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                        $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;

            case 'AUDIT':
                switch ($choice) {
                    case '1':
                        $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                        return true;
                    case '2':
                        $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, 'yesterday');
                        return true;
                    case '0':
                        Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                        $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        return true;
                }
                break;
        }

        return false;
    }

    /**
     * Handle Direct USSD Shortcode Syntax (e.g. 1.1, 1*1, 2.1, 3.2).
     */
    protected function handleUssdDirectAction(
        int $cat,
        int $action,
        string $extra,
        string $senderPhone,
        string $tenantId,
        string $tenantName,
        Setting $setting,
        ?int $userWarehouseId,
        bool $isExecutive,
        ?User $user,
        ?Setting $ownerSetting
    ): void {
        $stateKey = "wa_nav_state_{$senderPhone}";

        switch ($cat) {
            case 1: // Sales & Revenue
                Cache::put($stateKey, 'SALES', now()->addMinutes(30));
                switch ($action) {
                    case 1:
                        $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 2:
                        $this->dispatchCashDrawer($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 3:
                        $this->dispatchSoldItems($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 4:
                        $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 5:
                        $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, 'yesterday');
                        break;
                    default:
                        $this->dispatchSalesSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        break;
                }
                break;

            case 2: // Inventory & Stock
                Cache::put($stateKey, 'STOCK', now()->addMinutes(30));
                switch ($action) {
                    case 1:
                        if (!empty($extra)) {
                            $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $extra);
                        } else {
                            Cache::put($stateKey, 'AWAIT_STOCK_QUERY', now()->addMinutes(15));
                            $msg = "🔍 *Check Product Stock*\n\nPlease reply with the product name or barcode (e.g. *Peak Milk* or *Indomie*).";
                            $this->whatsAppService->sendMessage($senderPhone, $this->appendFooter($msg, "Reply with item name, or *0* for Main Menu"));
                        }
                        break;
                    case 2:
                        $this->dispatchAllStockSummary($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        break;
                    case 3:
                        $this->dispatchLowStockAlert($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 4:
                        $this->dispatchTransferWaybills($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        break;
                    default:
                        $this->dispatchStockSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        break;
                }
                break;

            case 3: // Debt & Customers
                Cache::put($stateKey, 'DEBT', now()->addMinutes(30));
                switch ($action) {
                    case 1:
                        $this->dispatchCustomerDebtBook($senderPhone, $tenantId, '');
                        break;
                    case 2:
                        if (!empty($extra)) {
                            $this->dispatchCustomerDebtBook($senderPhone, $tenantId, $extra);
                        } else {
                            Cache::put($stateKey, 'AWAIT_DEBT_QUERY', now()->addMinutes(15));
                            $msg = "👤 *Lookup Customer Debt Profile*\n\nPlease reply with the customer name, phone number, or code (e.g. *Okon* or *08012345678*).";
                            $this->whatsAppService->sendMessage($senderPhone, $this->appendFooter($msg, "Reply with customer name, or *0* for Main Menu"));
                        }
                        break;
                    case 3:
                        $this->dispatchCustomerDebtBook($senderPhone, $tenantId, 'today');
                        break;
                    default:
                        $this->dispatchDebtSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        break;
                }
                break;

            case 4: // Cashiers & Tills
                Cache::put($stateKey, 'CASHIERS', now()->addMinutes(30));
                switch ($action) {
                    case 1:
                        $this->dispatchCashierBreakdown($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 2:
                        $this->dispatchCashierBreakdown($senderPhone, $tenantId, $userWarehouseId, $isExecutive, 'yesterday');
                        break;
                    default:
                        $this->dispatchCashiersSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        break;
                }
                break;

            case 5: // Logistics & Waybills
                Cache::put($stateKey, 'LOGISTICS', now()->addMinutes(30));
                switch ($action) {
                    case 1:
                        $this->dispatchTransferWaybills($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        break;
                    default:
                        $this->dispatchLogisticsSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        break;
                }
                break;

            case 6: // Audit Log & Security
                Cache::put($stateKey, 'AUDIT', now()->addMinutes(30));
                switch ($action) {
                    case 1:
                        $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $extra);
                        break;
                    case 2:
                        $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, 'yesterday');
                        break;
                    default:
                        $this->dispatchAuditSubMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                        break;
                }
                break;

            case 7: // Full Closing Audit (REPORT)
                $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $extra);
                break;

            default:
                Cache::put($stateKey, 'ROOT', now()->addMinutes(30));
                $this->dispatchRootMainMenu($senderPhone, $user, $userWarehouseId, $isExecutive);
                break;
        }
    }

    /**
     * Dispatch Customer Debt Book & Debtor Lookup.
     */
    protected function dispatchCustomerDebtBook(string $phone, string $tenantId, string $query): void
    {
        // 1. If searching for a specific customer: e.g. "DEBT Okon"
        if (!empty($query)) {
            $customer = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('phone', 'like', "%{$query}%")
                      ->orWhere('customer_code', 'like', "%{$query}%");
                })
                ->first();

            if (!$customer) {
                $msg = "🔍 *No customer found matching:* \"{$query}\"\n\nReply *DEBT* to view top owing customers across the store.";
                $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
                return;
            }

            $recentLedgers = $customer->ledgers()->take(4)->get();
            $ledgerLines = "";
            foreach ($recentLedgers as $led) {
                $time = Carbon::parse($led->created_at)->format('d M, h:i A');
                $typeSign = $led->type === 'PAYMENT' ? '-' : '+';
                $ledgerLines .= "   • [{$time}] {$led->type}: *{$typeSign}₦" . number_format($led->amount, 0) . "* (" . Str::limit($led->notes, 25) . ")\n";
            }

            $creditLimit = (float) ($customer->credit_limit ?? 0);
            $limitText = $creditLimit > 0 ? "₦" . number_format($creditLimit, 0) : "Unlimited";

            $msg = "👤 *Customer Debt Profile — Victorious Market POS*\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "• Customer: *{$customer->name}*\n"
                . "• Phone: *{$customer->phone}*\n"
                . "• Code: `{$customer->customer_code}`\n"
                . "• 💰 *Current Outstanding Debt: ₦" . number_format($customer->total_debt, 2) . "*\n"
                . "• Credit Limit: *{$limitText}*\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "📋 *Recent Debt & Repayment Ledger:*\n"
                . ($ledgerLines ?: "   • No recent transactions recorded.\n") . "\n"
                . "Reply *DEBT* to view the full debtor summary.";

            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        // 2. Full Debtor Book Summary
        $totalDebt = (float) Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->sum('total_debt');
        $debtorCount = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('total_debt', '>', 0)->count();

        // Debt collected today
        $todayRecoveries = abs((float) CustomerLedger::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->where('type', 'PAYMENT')
            ->where('notes', 'not like', '[REVERSED%')
            ->sum('amount'));

        $topDebtors = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('total_debt', '>', 0)
            ->orderByDesc('total_debt')
            ->take(10)
            ->get();

        $debtorLines = "";
        $i = 1;
        foreach ($topDebtors as $d) {
            $debtorLines .= "{$i}. *{$d->name}* ({$d->phone})\n   • Outstanding: *₦" . number_format($d->total_debt, 2) . "*\n";
            $i++;
        }

        if (empty($debtorLines)) {
            $debtorLines = "🟢 *Zero Unpaid Debts!* All customer accounts are fully settled.\n";
        }

        $msg = "📖 *Customer Debt Book — Victorious Market POS*\n"
            . "📅 Date: *" . now()->format('D, d M Y (h:i A)') . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "💰 *Total Store Receivables / Debt:* ₦" . number_format($totalDebt, 2) . "\n"
            . "👥 *Total Customers Owing:* {$debtorCount} customers\n"
            . "💵 *Debt Recovered Today:* ₦" . number_format($todayRecoveries, 2) . "\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📋 *Top Outstanding Debtors:*\n\n"
            . $debtorLines . "\n"
            . "💡 *Tip:* Reply *DEBT <name>* to check a specific customer's payment history.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Cashier & Till Performance Breakdown.
     */
    protected function dispatchCashierBreakdown(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive, string $argument): void
    {
        [$targetDate, $targetWarehouseId] = $this->parseDateAndBranch($argument, $tenantId, $userWarehouseId, $isExecutive);

        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();
        $dateStartIso = $dateStart->toIso8601String();
        $dateEndIso = $dateEnd->toIso8601String();

        $query = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
            ->where(function ($q) use ($dateStart, $dateEnd, $dateStartIso, $dateEndIso) {
                $q->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
                  ->orWhereBetween('created_at', [$dateStart, $dateEnd]);
            });

        if ($targetWarehouseId) {
            $query->where('warehouse_id', $targetWarehouseId);
        }

        $sales = $query->get();

        if ($sales->isEmpty()) {
            $branchLabel = $targetWarehouseId ? (Warehouse::withoutGlobalScopes()->find($targetWarehouseId)?->name ?? "Branch #{$targetWarehouseId}") : "All Branches";
            $msg = "💼 *Cashier Performance — Victorious Market POS*\n"
                . "🏬 Branch: *{$branchLabel}*\n"
                . "📅 Date: *" . $targetDate->format('D, d M Y') . "*\n"
                . "-------------------------------------\n"
                . "ℹ️ No cashier sales transactions recorded for this date.\n\n"
                . "Reply *SALES* for today's summary or *CASH* for drawer balance.";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        // Group by cashier (userName or userId)
        $cashierStats = $sales->groupBy(fn($s) => $s->userName ?: ($s->userId ?: 'Till Cashier'));

        $lines = "";
        $i = 1;
        foreach ($cashierStats as $cashierName => $cSales) {
            $cTotal = (float) $cSales->sum('totalAmount');
            $cInvoices = $cSales->count();
            $cCash = (float) $cSales->sum('cashAmount');
            $cPos = (float) $cSales->sum('posAmount');
            $cTransfer = (float) $cSales->sum('transferAmount');

            $lines .= "{$i}️⃣ *{$cashierName}*\n"
                . "   • Invoices Completed: *{$cInvoices} sales*\n"
                . "   • Gross Sales: *₦" . number_format($cTotal, 2) . "*\n"
                . "   • Cash Collected: *₦" . number_format($cCash, 0) . "* | POS: *₦" . number_format($cPos, 0) . "*\n";
            if ($cTransfer > 0) {
                $lines .= "   • Transfers: *₦" . number_format($cTransfer, 0) . "*\n";
            }
            $lines .= "\n";
            $i++;
        }

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $msg = "💼 *Cashier & Till Performance — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . $lines
            . "Reply *CASH* to verify physical currency or *REPORT* for closing audit.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Dispatch Inter-Branch Transfers & Waybills Radar.
     */
    protected function dispatchTransferWaybills(string $phone, string $tenantId, ?int $userWarehouseId, bool $isExecutive): void
    {
        $query = Transfer::withoutGlobalScopes()
            ->with(['sourceWarehouse', 'destinationWarehouse', 'items'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'PENDING'])
            ->latest('created_at')
            ->take(8);

        if ($userWarehouseId) {
            $query->where(function ($q) use ($userWarehouseId) {
                $q->where('source_warehouse_id', $userWarehouseId)
                  ->orWhere('destination_warehouse_id', $userWarehouseId);
            });
        }

        $transfers = $query->get();

        if ($transfers->isEmpty()) {
            $msg = "🚚 *Waybills & Inter-Branch Transfers — Victorious Market POS*\n"
                . "━━━━━━━━━━━━━━━━━━━━\n"
                . "🟢 *No transfers currently in transit!*\n"
                . "All warehouse waybills have been successfully received and stocked.\n\n"
                . "Reply *STOCK ALL* to view total branch inventory holdings.";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
            return;
        }

        $lines = "";
        $i = 1;
        foreach ($transfers as $tr) {
            $src = $tr->sourceWarehouse?->name ?? "Branch #{$tr->source_warehouse_id}";
            $dest = $tr->destinationWarehouse?->name ?? "Branch #{$tr->destination_warehouse_id}";
            $time = $tr->dispatched_at ? Carbon::parse($tr->dispatched_at)->format('d M, h:i A') : 'Pending';
            $itemsCount = $tr->items->count();
            $totalUnits = (int) $tr->items->sum('dispatched_qty');

            $lines .= "{$i}️⃣ *Waybill #{$tr->transfer_no}* [{$tr->status}]\n"
                . "   • Route: *{$src}* ➔ *{$dest}*\n"
                . "   • Cargo: *{$totalUnits} units* ({$itemsCount} products)\n"
                . "   • Dispatched: {$time}\n\n";
            $i++;
        }

        $msg = "🚚 *Active Inter-Branch Transfers / Waybills*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . $lines
            . "Reply *REPORT* for daily reconciliation or *STOCK <item>* to check stock.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg, "Reply *1* for Sales Menu | *2* for Stock Menu | *0* for Main Menu"));
    }

    /**
     * Append Universal Victorious Market Footer with USSD Quick Navigation.
     */
    protected function appendFooter(string $message, ?string $quickNav = null): string
    {
        $nav = $quickNav ?: "📲 *USSD Quick Navigation:*\nReply *0* for Main Menu | *1* for Sales | *2* for Stock";
        return $message
            . "\n━━━━━━━━━━━━━━━━━━━━━━━━━\n"
            . $nav . "\n"
            . "─────────────────────────\n"
            . "Powered by Victorious Market POS";
    }
}
