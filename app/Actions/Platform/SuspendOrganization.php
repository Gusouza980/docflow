<?php

namespace App\Actions\Platform;

use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class SuspendOrganization
{
    public function __construct(
        private RecordPlatformAuditLog $recordPlatformAuditLog,
    ) {}

    public function execute(
        Organization $organization,
        User $platformAdmin,
        ?string $reason = null,
        ?Request $request = null,
    ): Organization {
        $organization->update(['status' => Organization::STATUS_SUSPENDED]);

        $this->recordPlatformAuditLog->execute(
            action: PlatformAuditLog::ACTION_ORGANIZATION_SUSPENDED,
            platformAdmin: $platformAdmin,
            subject: $organization,
            metadata: array_filter(['reason' => $reason]),
            request: $request,
        );

        return $organization->fresh();
    }
}
