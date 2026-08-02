<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function record(
        ?User $actor,
        string $action,
        string $targetType,
        ?string $targetId = null,
        ?string $targetName = null,
        ?string $details = null,
        ?array $meta = null
    ): void {
        AuditLog::create([
            'id' => Str::uuid(),
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->activeRole()?->nama,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'details' => $details,
            'meta' => $meta,
        ]);
    }
}
