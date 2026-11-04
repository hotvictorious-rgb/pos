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
use App\Models\SalesReturn;
use App\Models\Activity;
use App\Services\Notification\WhatsAppService;
use App\Console\Commands\SendDailyWhatsAppReportCommand;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
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
            // 3. COMMAND DISPATCHER (DETERMINISTIC, RESILIENT & EXTENDED)
            // ─────────────────────────────────────────────────────────────
            $parts = preg_split('/\s+/', $text, 2);
            $firstWord = strtoupper(trim($parts[0] ?? ''));
            $argument = trim($parts[1] ?? '');

            switch ($firstWord) {
                case '1':
                case 'REPORT':
                case 'CLOSE':
                case 'CLOSING':
                case 'SUMMARY':
                case 'AUDIT':
                    $this->dispatchOnDemandReport($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                    break;

                case '2':
                case 'SALES':
                case 'PULSE':
                case 'TODAY':
                case 'REVENUE':
                    $this->dispatchSalesPulse($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case '3':
                case 'CASH':
                case 'DRAWER':
                case 'BALANCE':
                case 'TILL':
                    $this->dispatchCashDrawer($senderPhone, $tenantId, $tenantName, $setting, $userWarehouseId, $isExecutive, $argument);
                    break;

                case '4':
                case 'SOLD':
                case 'ITEMS':
                case 'BREAKDOWN':
                case 'PRODUCTS_SOLD':
                    $this->dispatchSoldItems($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case '5':
                case 'STOCK':
                case 'PRICE':
                case 'CHECK':
                case 'FIND':
                case 'INVENTORY':
                    if (strtoupper($argument) === 'ALL' || empty($argument)) {
                        if (strtoupper($argument) === 'ALL') {
                            $this->dispatchAllStockSummary($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                        } else {
                            $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $argument);
                        }
                    } else {
                        $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $argument);
                    }
                    break;

                case '6':
                case 'ALL':
                case 'ALL_STOCK':
                    $this->dispatchAllStockSummary($senderPhone, $tenantId, $userWarehouseId, $isExecutive);
                    break;

                case '7':
                case 'LOW':
                case 'ALERT':
                case 'RESTOCK':
                case 'OUT':
                    $this->dispatchLowStockAlert($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case '8':
                case 'LOG':
                case 'ACTIVITY':
                case 'TIMELINE':
                case 'HISTORY':
                case 'VOIDS':
                    $this->dispatchOperationalTimeline($senderPhone, $tenantId, $userWarehouseId, $isExecutive, $argument);
                    break;

                case 'MENU':
                case 'HELP':
                case 'HI':
                case 'HELLO':
                case 'START':
                case 'COMMANDS':
                case 'INFO':
                    $this->dispatchHelpMenu($senderPhone, $user, $ownerSetting, $userWarehouseId, $isExecutive);
                    break;

                default:
                    // Smart Product Lookup Fallback: If user sent a product name or barcode directly
                    if ($this->hasMatchingProducts($tenantId, $text)) {
                        $this->dispatchStockLookup($senderPhone, $tenantId, $userWarehouseId, $text);
                    } else {
                        $this->dispatchHelpMenu($senderPhone, $user, $ownerSetting, $userWarehouseId, $isExecutive);
                    }
                    break;
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

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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

        $branchLabel = 'All Branches Combined';
        if ($targetWarehouseId) {
            $wh = Warehouse::withoutGlobalScopes()->find($targetWarehouseId);
            if ($wh) $branchLabel = $wh->name;
        }

        $timeLabel = $targetDate->isToday() ? " (" . now()->format('h:i A') . ")" : "";

        $msg = "⚡ *Live Sales Pulse — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "📅 Date: *" . $targetDate->format('D, d M Y') . "{$timeLabel}*\n"
            . "-------------------------------------\n"
            . "💰 Gross Sales: *₦" . number_format($totalSales, 2) . "*\n"
            . "🧾 Total Transactions: *{$count} invoice" . ($count === 1 ? '' : 's') . "*\n"
            . "💵 Settled Immediately: *₦" . number_format($paidNow, 2) . "*\n"
            . "⏳ Customer Credit / Unpaid: *₦" . number_format($debt, 2) . "*\n"
            . "-------------------------------------\n"
            . "💳 *Tender Breakdown:*\n"
            . "   • Cash: *₦" . number_format($cashTotal, 2) . "*\n"
            . "   • POS Terminal: *₦" . number_format($posTotal, 2) . "*\n"
            . "   • Bank Transfer: *₦" . number_format($transferTotal, 2) . "*\n\n"
            . "Reply *SOLD* for itemized product list or *REPORT* for closing audit.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
            return;
        }

        $msg = "⚠️ *Low Stock Radar — Victorious Market POS*\n"
            . "🏬 Branch: *{$branchLabel}*\n"
            . "The following items require immediate restock / purchase order:\n\n"
            . $itemsText . "\n"
            . "Reply *STOCK <name>* to check any other specific product.";

        $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
    }

    /**
     * Comprehensive Product & Inventory Intelligence Lookup.
     * Displays: Price, physical stock, units sold today, allocated orders, net available, branch breakdown, category, barcode.
     */
    protected function dispatchStockLookup(string $phone, string $tenantId, ?int $userWarehouseId, string $queryStr): void
    {
        if (empty($queryStr)) {
            $msg = "ℹ️ *How to check stock & prices:*\nReply *STOCK <product name or barcode>*\nExample: `STOCK milk` or `STOCK 10023`\n\n💡 *Tip:* You can also simply type the item name directly!";
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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
            $this->whatsAppService->sendMessage($phone, $this->appendFooter($msg));
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

        $menu = "🤖 *Victorious Market POS — Executive Command Console*\n"
            . "👤 Role: *{$roleName}*\n"
            . "🏬 Scope: *{$branchName}*\n"
            . "📅 Date: *" . now()->format('D, d M Y') . "*\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "Reply with any number or keyword:\n\n"
            . "1️⃣ *REPORT* — Full closing reconciliation audit\n"
            . "   ↳ Tip: Send `REPORT yesterday` or `REPORT 2`\n\n"
            . "2️⃣ *SALES*  — Live gross sales & tender breakdown\n"
            . "   ↳ Tip: Send `SALES yesterday`\n\n"
            . "3️⃣ *CASH*   — Reconciled cash in drawer for bank deposit\n"
            . "   ↳ Cash Sales + Debt Recoveries - Refunds\n\n"
            . "4️⃣ *SOLD*   — Products sold today & remaining shelf stock\n"
            . "   ↳ Tip: Send `SOLD yesterday`\n\n"
            . "5️⃣ *STOCK <item>* — Instant price, physical & branch availability\n"
            . "   ↳ You can also just type any item name directly!\n\n"
            . "6️⃣ *STOCK ALL* — Total inventory units & store retail value\n\n"
            . "7️⃣ *LOW*    — Restock radar (≤ safety threshold)\n\n"
            . "8️⃣ *LOG*    — Operational audit trail of everything that happened\n";

        if ($isExecutive) {
            $menu .= "\n💡 *Multi-Branch Option:*\nSend `REPORT 2` or `SALES 2` to audit any specific branch.\n";
        }

        $menu .= "\n_Victorious Market POS Engine_";

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
