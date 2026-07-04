<?php

namespace App\Actions\Platform;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordPlatformAuditLog
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $action,
        User $platformAdmin,
        ?Model $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): PlatformAuditLog {
        return PlatformAuditLog::create([
            'platform_admin_user_id' => $platformAdmin->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
