<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\Activity;

class CronFreeWhatsAppReportMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Perform background reconciliation check after response has been delivered to client.
     * Guaranteed 0ms added latency to browsing cashiers or store visitors.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Don't trigger on static assets, health checks, or worker pings
        if ($request->is('up', 'favicon.ico', 'sw.js', 'manifest.json', 'css/*', 'js/*', 'icons/*')) {
            return;
        }

        try {
            $now = Carbon::now('Africa/Lagos');
            $todayDate = $now->toDateString();
            $currentTime = $now->format('H:i');

            // Quick cache check: if checked and marked done for today, exit immediately in 0.01ms
            $cacheKey = "whatsapp_cron_free_status_{$todayDate}";
            if (Cache::has($cacheKey)) {
                return;
            }

            // Check if any tenant has automated WhatsApp reporting enabled
            $setting = Setting::withoutGlobalScopes()
                ->where('whatsapp_report_enabled', true)
                ->whereNotNull('owner_whatsapp_phone')
                ->where('owner_whatsapp_phone', '!=', '')
                ->first();

            if (!$setting) {
                Cache::put($cacheKey, 'disabled_or_empty', 600); // Recheck in 10 minutes
                return;
            }

            $dispatchTime = $setting->whatsapp_report_time ?: '21:00';

            // Only trigger if store time is at or past the scheduled closing dispatch time
            if ($currentTime < $dispatchTime) {
                return;
            }

            // Check Activity log to ensure today's report hasn't already been recorded
            $alreadySent = Activity::withoutGlobalScopes()
                ->where('type', 'WHATSAPP_DAILY_REPORT_SENT')
                ->whereDate('created_at', $todayDate)
                ->exists();

            if ($alreadySent) {
                Cache::put($cacheKey, 'already_sent', 3600 * 12); // Marked complete for the day
                return;
            }

            // Acquire atomic mutex lock for 120 seconds to prevent concurrent worker duplication
            $lock = Cache::lock("lock_whatsapp_dispatch_{$todayDate}", 120);
            if ($lock->get()) {
                Log::info("[Cron-Free WhatsApp Trigger] Dispatching automated daily closing report for {$todayDate} via web pseudo-cron...");

                Artisan::call('pos:send-daily-whatsapp', [
                    '--date' => $todayDate,
                ]);

                Cache::put($cacheKey, 'dispatched', 3600 * 12);
                $lock->release();
            }
        } catch (\Throwable $e) {
            // Fail safely: Never throw exceptions during post-response termination
            Log::warning("[Cron-Free WhatsApp Trigger] Notice during pseudo-cron check: " . $e->getMessage());
        }
    }
}
