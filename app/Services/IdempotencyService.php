<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    /**
     * Executes an operation with robust request fingerprinting and atomic replay protection.
     *
     * @param string $operation Unique identifier of the operation (e.g., 'checkout', 'debt_payment')
     * @param string $idempotencyKey Client-supplied key
     * @param string $tenantId Active tenant ID for strict multi-tenant isolation
     * @param string $userId Acting user ID for authorization scoping
     * @param array $payload Incoming request parameters to calculate payload fingerprint
     * @param \Closure $callback Closure containing the transactional business operation
     * @return mixed
     *
     * @throws \InvalidArgumentException When key is reused with a different request or unauthorized user
     */
    public function execute(string $operation, string $idempotencyKey, string $tenantId, string $userId, array $payload, \Closure $callback): mixed
    {
        $cleanKey = trim($idempotencyKey);
        if (empty($cleanKey)) {
            return $callback();
        }

        // Normalize payload: remove volatile session/CSRF tokens
        $normalizedPayload = $payload;
        unset($normalizedPayload['_token'], $normalizedPayload['timestamp'], $normalizedPayload['time']);
        ksort($normalizedPayload);

        $fingerprint = hash('sha256', json_encode([
            'operation' => $operation,
            'tenant_id' => $tenantId,
            'payload' => $normalizedPayload,
        ]));

        $cacheKey = "idempotency:{$tenantId}:{$operation}:{$cleanKey}";
        $lockKey = "lock:{$cacheKey}";

        // Atomic lock to serialize concurrent identical requests
        $lock = Cache::lock($lockKey, 15);

        return $lock->block(10, function () use ($cacheKey, $cleanKey, $fingerprint, $userId, $callback) {
            $cached = Cache::get($cacheKey);

            if ($cached) {
                // Check 1: Request Fingerprint Invariance
                if (($cached['fingerprint'] ?? '') !== $fingerprint) {
                    throw new \InvalidArgumentException(
                        "Idempotency Conflict: Key '{$cleanKey}' has already been used for a different request payload."
                    );
                }

                // Check 2: User Authorization Scoping
                if (($cached['user_id'] ?? '') !== $userId) {
                    throw new \InvalidArgumentException(
                        "Idempotency Authorization Violation: Key '{$cleanKey}' cannot be reused across different user accounts."
                    );
                }

                return $cached['result'];
            }

            // Execute the business operation
            $result = $callback();

            // Store result with 24-hour TTL after successful execution
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'user_id' => $userId,
                'result' => $result,
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            return $result;
        });
    }
}
