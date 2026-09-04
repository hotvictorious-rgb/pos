<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\IdempotencyRecord;
use Illuminate\Support\Str;

class IdempotencyService
{
    /**
     * Executes an operation with robust request fingerprinting, L1 cache acceleration,
     * and durable database-backed replay protection.
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

        return $lock->block(10, function () use ($cacheKey, $cleanKey, $fingerprint, $tenantId, $operation, $userId, $callback) {
            // ── L1: In-Memory / Distributed Cache Fast-Path ──
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

            // ── L2: Durable Database Persistence (Survives Cache Flush / Restart / Eviction) ──
            $persistentRecord = IdempotencyRecord::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('operation', $operation)
                ->where('idempotency_key', $cleanKey)
                ->first();

            if ($persistentRecord && $persistentRecord->status !== 'FAILED') {
                // Check 1: Request Fingerprint Invariance
                if ($persistentRecord->payload_fingerprint !== $fingerprint) {
                    throw new \InvalidArgumentException(
                        "Idempotency Conflict: Key '{$cleanKey}' has already been used for a different request payload."
                    );
                }

                // Check 2: User Authorization Scoping
                if ($persistentRecord->user_id !== $userId) {
                    throw new \InvalidArgumentException(
                        "Idempotency Authorization Violation: Key '{$cleanKey}' cannot be reused across different user accounts."
                    );
                }

                if ($persistentRecord->status === 'COMPLETED') {
                    $result = $this->deserializeResult($persistentRecord->response_data);

                    // Repopulate L1 cache
                    Cache::put($cacheKey, [
                        'fingerprint' => $fingerprint,
                        'user_id' => $userId,
                        'result' => $result,
                        'created_at' => $persistentRecord->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    ], now()->addHours(24));

                    return $result;
                }

                // If currently processing within 60s window, prevent concurrent re-entry
                if ($persistentRecord->status === 'PROCESSING' && $persistentRecord->updated_at && $persistentRecord->updated_at->diffInSeconds(now()) < 60) {
                    throw new \InvalidArgumentException(
                        "Idempotency Conflict: Operation '{$operation}' with key '{$cleanKey}' is currently processing. Please wait for completion."
                    );
                }
            }

            // Mark record as PROCESSING before execution
            IdempotencyRecord::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'operation' => $operation,
                    'idempotency_key' => $cleanKey,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'payload_fingerprint' => $fingerprint,
                    'status' => 'PROCESSING',
                    'response_data' => null,
                ]
            );

            // ── Atomic Execution: Run the business operation and transition to COMPLETED in the same transaction ──
            try {
                $result = \Illuminate\Support\Facades\DB::transaction(function () use ($callback, $tenantId, $operation, $cleanKey) {
                    $res = $callback();

                    IdempotencyRecord::withoutGlobalScopes()->where([
                        'tenant_id' => $tenantId,
                        'operation' => $operation,
                        'idempotency_key' => $cleanKey,
                    ])->update([
                        'status' => 'COMPLETED',
                        'response_data' => json_encode($this->serializeResult($res)),
                        'updated_at' => now(),
                    ]);

                    return $res;
                });
            } catch (\Throwable $e) {
                IdempotencyRecord::withoutGlobalScopes()->where([
                    'tenant_id' => $tenantId,
                    'operation' => $operation,
                    'idempotency_key' => $cleanKey,
                ])->update([
                    'status' => 'FAILED',
                    'response_data' => json_encode(['error' => $e->getMessage()]),
                    'updated_at' => now(),
                ]);

                throw $e;
            }

            // Store in L1 Cache with 24-hour TTL
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'user_id' => $userId,
                'result' => $result,
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            return $result;
        });
    }

    /**
     * Serializes result for durable storage.
     */
    protected function serializeResult(mixed $result): mixed
    {
        if ($result instanceof \Illuminate\Database\Eloquent\Model) {
            return [
                '__type' => 'eloquent_model',
                '__class' => get_class($result),
                'id' => (string) $result->getKey(),
                'attributes' => $result->getAttributes(),
            ];
        }

        return $result;
    }

    /**
     * Deserializes result back to original model or data structure.
     */
    protected function deserializeResult(mixed $data): mixed
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        if (is_array($data) && ($data['__type'] ?? null) === 'eloquent_model') {
            $class = $data['__class'] ?? null;
            $id = $data['id'] ?? null;
            if ($class && class_exists($class) && $id) {
                $model = $class::withoutGlobalScopes()->find($id);
                if ($model) {
                    return $model;
                }
                $instance = new $class();
                $instance->setRawAttributes($data['attributes'] ?? [], true);
                $instance->exists = true;
                return $instance;
            }
        }

        return $data;
    }
}
