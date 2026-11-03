<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    /**
     * Normalize a phone number to standard E.164 without '+' prefix (required by WhatsApp APIs).
     * Handles Nigerian local 080/090/070/081 formats cleanly (08012345678 -> 2348012345678).
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Nigerian local format starting with 0 (11 digits: e.g. 08031234567)
        if (strlen($cleaned) === 11 && str_starts_with($cleaned, '0')) {
            return '234' . substr($cleaned, 1);
        }

        return $cleaned;
    }

    /**
     * Send a WhatsApp message to a recipient using the configured gateway.
     *
     * @param string $toPhone Recipient phone number
     * @param string $message Formatted text message
     * @param string|null $tenantId Scoped tenant identifier
     * @return array Result of the dispatch operation ['success' => bool, 'gateway' => string, 'details' => mixed]
     */
    public function sendMessage(string $toPhone, string $message, ?string $tenantId = null): array
    {
        $normalizedPhone = $this->formatPhoneNumber($toPhone);

        if (empty($normalizedPhone)) {
            return [
                'success' => false,
                'gateway' => 'none',
                'error' => 'Invalid or empty phone number provided.',
            ];
        }

        $gateway = config('whatsapp.gateway', 'log');
        $enabled = config('whatsapp.enabled', false);

        // If WhatsApp is disabled or set to log, log locally without external HTTP calls
        if (!$enabled || $gateway === 'log') {
            Log::info("[WhatsApp Service: LOG GATEWAY] Dispatching to {$normalizedPhone}:", [
                'to' => $normalizedPhone,
                'tenant_id' => $tenantId,
                'preview' => mb_substr($message, 0, 120) . '...',
            ]);

            return [
                'success' => true,
                'gateway' => 'log',
                'simulated' => true,
                'to' => $normalizedPhone,
            ];
        }

        try {
            switch ($gateway) {
                case 'meta':
                    return $this->sendViaMetaCloudApi($normalizedPhone, $message);

                case 'termii':
                    return $this->sendViaTermii($normalizedPhone, $message);

                case 'twilio':
                    return $this->sendViaTwilio($normalizedPhone, $message);

                case 'generic_webhook':
                    return $this->sendViaGenericWebhook($normalizedPhone, $message, $tenantId);

                default:
                    Log::warning("[WhatsApp Service] Unknown gateway '{$gateway}', falling back to log.");
                    return [
                        'success' => true,
                        'gateway' => 'fallback_log',
                        'simulated' => true,
                        'to' => $normalizedPhone,
                    ];
            }
        } catch (Throwable $e) {
            Log::error("[WhatsApp Service Error] Failed to dispatch via {$gateway}: " . $e->getMessage(), [
                'to' => $normalizedPhone,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send via official Meta WhatsApp Business Cloud API.
     */
    protected function sendViaMetaCloudApi(string $phone, string $message): array
    {
        $phoneId = config('whatsapp.meta.phone_number_id');
        $token = config('whatsapp.meta.token');
        $apiUrl = rtrim(config('whatsapp.meta.api_url', 'https://graph.facebook.com/v19.0'), '/');

        if (empty($phoneId) || empty($token)) {
            throw new \RuntimeException('Meta Cloud API phone_number_id or token is not configured in .env');
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->post("{$apiUrl}/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'gateway' => 'meta',
                'message_id' => $response->json('messages.0.id'),
            ];
        }

        throw new \RuntimeException('Meta API error: ' . $response->body());
    }

    /**
     * Send via Termii WhatsApp API (Nigeria).
     */
    protected function sendViaTermii(string $phone, string $message): array
    {
        $apiKey = config('whatsapp.termii.api_key');
        $apiUrl = rtrim(config('whatsapp.termii.api_url', 'https://api.ng.termii.com/api'), '/');
        $deviceId = config('whatsapp.termii.device_id');

        if (empty($apiKey)) {
            throw new \RuntimeException('Termii API key is not configured in .env');
        }

        $payload = [
            'to' => $phone,
            'from' => $deviceId ?: 'Termii',
            'sms' => $message,
            'type' => 'plain',
            'channel' => 'whatsapp',
            'api_key' => $apiKey,
        ];

        $response = Http::timeout(15)->post("{$apiUrl}/sms/send", $payload);

        if ($response->successful()) {
            return [
                'success' => true,
                'gateway' => 'termii',
                'response' => $response->json(),
            ];
        }

        throw new \RuntimeException('Termii API error: ' . $response->body());
    }

    /**
     * Send via Twilio WhatsApp API.
     */
    protected function sendViaTwilio(string $phone, string $message): array
    {
        $sid = config('whatsapp.twilio.sid');
        $token = config('whatsapp.twilio.token');
        $from = config('whatsapp.twilio.from');

        if (empty($sid) || empty($token) || empty($from)) {
            throw new \RuntimeException('Twilio credentials (sid, token, from) are not configured in .env');
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => "whatsapp:+{$phone}",
                'From' => str_starts_with($from, 'whatsapp:') ? $from : "whatsapp:{$from}",
                'Body' => $message,
            ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'gateway' => 'twilio',
                'message_sid' => $response->json('sid'),
            ];
        }

        throw new \RuntimeException('Twilio API error: ' . $response->body());
    }

    /**
     * Send via generic JSON webhook.
     */
    protected function sendViaGenericWebhook(string $phone, string $message, ?string $tenantId): array
    {
        $url = config('whatsapp.generic_webhook.url');
        $token = config('whatsapp.generic_webhook.token');

        if (empty($url)) {
            throw new \RuntimeException('Generic webhook URL is not configured in .env');
        }

        $request = Http::timeout(15);
        if (!empty($token)) {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, [
            'phone' => $phone,
            'message' => $message,
            'tenant_id' => $tenantId,
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'gateway' => 'generic_webhook',
                'status' => $response->status(),
            ];
        }

        throw new \RuntimeException('Generic webhook error: HTTP ' . $response->status() . ' - ' . $response->body());
    }

    /**
     * Build an elegant, highly structured WhatsApp daily report message.
     */
    public function buildDailyReportMessage(array $data): string
    {
        $currency = $data['currency'] ?? '₦';
        $businessName = $data['business_name'] ?? 'Victorious POS';
        $branchName = $data['branch_name'] ?? 'All Branches';
        $date = $data['date'] ?? now()->format('D, d M Y');

        $grossSales = number_format((float) ($data['gross_sales'] ?? 0), 2);
        $totalInvoices = (int) ($data['total_invoices'] ?? 0);
        $cashCollected = number_format((float) ($data['cash_collected'] ?? 0), 2);
        $posCollected = number_format((float) ($data['pos_collected'] ?? 0), 2);
        $transferCollected = number_format((float) ($data['transfer_collected'] ?? 0), 2);
        $debtsRecovered = number_format((float) ($data['debts_recovered'] ?? 0), 2);
        $newDebtsIncurred = number_format((float) ($data['new_debts'] ?? 0), 2);
        $returnsRefunded = number_format((float) ($data['returns_refunded'] ?? 0), 2);

        $reversalsCount = (int) ($data['reversals_count'] ?? 0);
        $reversalsAmount = number_format((float) ($data['reversals_amount'] ?? 0), 2);

        $expectedDrawerCash = number_format((float) ($data['expected_drawer_cash'] ?? 0), 2);

        $msg = "📊 *{$businessName} — DAILY CLOSING SUMMARY*\n";
        $msg .= "🏬 *Branch:* {$branchName}\n";
        $msg .= "📅 *Date:* {$date} (9:00 PM Reconciliation)\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💵 *FINANCIAL PERFORMANCE*\n";
        $msg .= "• *Gross Sales:* {$currency}{$grossSales} ({$totalInvoices} invoices)\n";
        $msg .= "• *Cash in Till:* {$currency}{$cashCollected}\n";
        $msg .= "• *POS Payments:* {$currency}{$posCollected}\n";
        if ((float) ($data['transfer_collected'] ?? 0) > 0) {
            $msg .= "• *Bank Transfers:* {$currency}{$transferCollected}\n";
        }
        $msg .= "• *Debt Recovery:* {$currency}{$debtsRecovered}\n";
        $msg .= "• *New Customer Debt:* {$currency}{$newDebtsIncurred}\n";
        if ((float) ($data['returns_refunded'] ?? 0) > 0) {
            $msg .= "• *Returns/Refunds Outflow:* {$currency}{$returnsRefunded}\n";
        }
        $msg .= "\n💼 *NET CASH IN TILL FOR DEPOSIT:* {$currency}{$expectedDrawerCash}\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";

        // Audit & Reversals Section
        $msg .= "🛡️ *SECURITY & REVERSALS*\n";
        if ($reversalsCount > 0) {
            $msg .= "• *Reversals Executed:* {$reversalsCount} ({$currency}{$reversalsAmount})\n";
            if (!empty($data['reversals_details'])) {
                foreach (array_slice($data['reversals_details'], 0, 3) as $rev) {
                    $msg .= "   ↳ {$rev['ref']}: {$currency}" . number_format($rev['amount'], 2) . " by {$rev['user']} ({$rev['reason']})\n";
                }
            }
        } else {
            $msg .= "• *Reversals Executed:* None (All transactions intact ✓)\n";
        }
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";

        // Branch Breakdown (if multi-branch aggregate)
        if (!empty($data['branches_breakdown']) && is_array($data['branches_breakdown'])) {
            $msg .= "🏢 *PER-BRANCH BREAKDOWN*\n";
            foreach ($data['branches_breakdown'] as $b) {
                $bCash = number_format((float) ($b['cash'] ?? 0), 2);
                $bSales = number_format((float) ($b['sales'] ?? 0), 2);
                $msg .= "• *{$b['name']}:* Sales {$currency}{$bSales} | Till Cash: {$currency}{$bCash}\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        }

        // Low stock alerts
        if (!empty($data['low_stock_items']) && is_array($data['low_stock_items'])) {
            $count = count($data['low_stock_items']);
            $msg .= "⚠️ *LOW STOCK ALERT ({$count} items)*\n";
            foreach (array_slice($data['low_stock_items'], 0, 5) as $item) {
                $msg .= "• {$item['name']}: *{$item['qty']} {$item['unit']}* remaining (min: {$item['min']})\n";
            }
            if ($count > 5) {
                $more = $count - 5;
                $msg .= "   ↳ ...and {$more} more low items in catalog.\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        }

        $msg .= "🔒 *Automated EOD Report — Victorious Market POS ERP*\n";
        $msg .= "Generated at: " . now()->format('H:i:s d/m/Y');

        return $msg;
    }
}

