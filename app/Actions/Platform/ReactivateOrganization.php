<?php

namespace App\Actions\Platform;

use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ReactivateOrganization
{
    public function __construct(
        private RecordPlatformAuditLog $recordPlatformAuditLog,
    ) {}

    public function execute(
        Organization $organization,
        User $platformAdmin,
        ?Request $request = null,
    ): Organization {
        $organization->update(['status' => Organization::STATUS_ACTIVE]);

        $this->recordPlatformAuditLog->execute(
            action: PlatformAuditLog::ACTION_ORGANIZATION_REACTIVATED,
            platformAdmin: $platformAdmin,
            subject: $organization,
            request: $request,
        );

        return $organization->fresh();
    }
}
