<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Activity extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'type',
        'description',
        'userId',
        'userName',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Authoritatively record a structured security/audit event.
     */
    public static function recordSecurityEvent(
        string $type,
        string $description,
        array $metadata = [],
        ?User $actor = null
    ): self {
        $req = request();
        $actor = $actor ?: \Illuminate\Support\Facades\Auth::user();
        $tenantId = $actor?->tenant_id ?: session('tenant_id');

        $baseMetadata = [
            'ip'           => $req ? $req->ip() : '127.0.0.1',
            'user_agent'   => $req ? substr((string) $req->userAgent(), 0, 500) : null,
            'request_id'   => ($req && $req->header('X-Request-ID')) ? $req->header('X-Request-ID') : (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'    => $tenantId,
            'warehouse_id' => $actor?->warehouse_id ?: session('active_warehouse_id'),
        ];

        return self::create([
            'id'          => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'   => $tenantId,
            'type'        => $type,
            'description' => $description,
            'userId'      => $actor?->id ?: 'system',
            'userName'    => $actor?->name ?: 'System',
            'timestamp'   => now()->toIso8601String(),
            'metadata'    => array_merge($baseMetadata, $metadata),
        ]);
    }
}
