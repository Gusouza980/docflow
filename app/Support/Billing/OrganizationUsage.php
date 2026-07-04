<?php

namespace App\Support\Billing;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\DocumentVersion;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;

class OrganizationUsage
{
    public function membersCount(Organization $organization): int
    {
        return OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->count();
    }

    public function pendingInvitationsCount(Organization $organization): int
    {
        return OrganizationInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationInvitation::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    public function seatCount(Organization $organization): int
    {
        return $this->membersCount($organization) + $this->pendingInvitationsCount($organization);
    }

    public function clientsCount(Organization $organization): int
    {
        return Client::query()
            ->where('organization_id', $organization->id)
            ->count();
    }

    public function portalAccessesCount(Organization $organization): int
    {
        return ClientPortalAccess::query()
            ->where('organization_id', $organization->id)
            ->where('status', ClientPortalAccess::STATUS_ACTIVE)
            ->count();
    }

    public function storageMb(Organization $organization): int
    {
        $bytes = (int) DocumentVersion::query()
            ->where('organization_id', $organization->id)
            ->sum('size');

        return (int) ceil($bytes / 1024 / 1024);
    }

    /**
     * @return array<string, int>
     */
    public function counts(Organization $organization): array
    {
        return [
            'max_members' => $this->seatCount($organization),
            'max_clients' => $this->clientsCount($organization),
            'max_storage_mb' => $this->storageMb($organization),
            'max_portal_accesses' => $this->portalAccessesCount($organization),
        ];
    }
}
