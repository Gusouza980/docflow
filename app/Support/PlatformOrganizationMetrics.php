<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Receivable;

class PlatformOrganizationMetrics
{
    /**
     * @return array{
     *     members_count: int,
     *     active_members_count: int,
     *     clients_count: int,
     *     open_receivables_count: int
     * }
     */
    public function for(Organization $organization): array
    {
        return [
            'members_count' => OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->count(),
            'active_members_count' => OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationMember::STATUS_ACTIVE)
                ->count(),
            'clients_count' => Client::query()
                ->where('organization_id', $organization->id)
                ->count(),
            'open_receivables_count' => Receivable::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
                ->count(),
        ];
    }
}
