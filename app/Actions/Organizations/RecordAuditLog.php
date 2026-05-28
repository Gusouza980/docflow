<?php

namespace App\Actions\Organizations;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordAuditLog
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        string $action,
        ?User $user = null,
        ?Organization $organization = null,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $organization?->id,
            'user_id' => $user?->id,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
