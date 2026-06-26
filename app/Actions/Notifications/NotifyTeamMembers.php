<?php

namespace App\Actions\Notifications;

use App\Models\Client;
use App\Models\InternalReminder;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Model;

class NotifyTeamMembers
{
    /**
     * @param  list<int>  $extraUserIds
     */
    public function execute(
        Organization $organization,
        Model $remindable,
        string $type,
        ?Client $client = null,
        bool $includeManagers = false,
        array $extraUserIds = [],
    ): void {
        $userIds = array_unique([
            ...$this->resolveUserIds($organization, $client, $includeManagers),
            ...$extraUserIds,
        ]);

        foreach ($userIds as $userId) {
            InternalReminder::updateOrCreate([
                'organization_id' => $organization->id,
                'user_id' => $userId,
                'remindable_type' => $remindable->getMorphClass(),
                'remindable_id' => $remindable->getKey(),
                'type' => $type,
            ], [
                'remind_at' => now(),
                'read_at' => null,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolveUserIds(Organization $organization, ?Client $client, bool $includeManagers): array
    {
        $userIds = [];

        if ($client?->primary_responsible_member_id) {
            $client->loadMissing('primaryResponsible');
            $userId = $client->primaryResponsible?->user_id;

            if ($userId) {
                $userIds[] = $userId;
            }
        }

        if ($includeManagers) {
            $managerIds = OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationMember::STATUS_ACTIVE)
                ->whereIn('role', [OrganizationMember::ROLE_ADMIN, OrganizationMember::ROLE_MANAGER])
                ->pluck('user_id')
                ->all();

            $userIds = [...$userIds, ...$managerIds];
        }

        if ($userIds === []) {
            $fallbackId = OrganizationMember::query()
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationMember::STATUS_ACTIVE)
                ->where('role', OrganizationMember::ROLE_ADMIN)
                ->value('user_id');

            if ($fallbackId) {
                $userIds[] = $fallbackId;
            }
        }

        return array_values(array_unique(array_filter($userIds)));
    }
}
