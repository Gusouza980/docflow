<?php

namespace App\Automations\Actions;

use App\Models\AutomationRule;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Model;

class NotifyOrganizationMembersAction
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(AutomationRule $rule, Model $subject, array $params, array $context = []): array
    {
        $roles = $params['roles'] ?? [
            OrganizationMember::ROLE_ADMIN,
            OrganizationMember::ROLE_MANAGER,
        ];

        $members = OrganizationMember::query()
            ->where('organization_id', $rule->organization_id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->whereIn('role', $roles)
            ->get();

        $created = 0;

        foreach ($members as $member) {
            InternalReminder::query()->firstOrCreate([
                'organization_id' => $rule->organization_id,
                'user_id' => $member->user_id,
                'remindable_type' => $subject->getMorphClass(),
                'remindable_id' => $subject->getKey(),
                'type' => 'automation_'.$rule->trigger,
                'remind_at' => now(),
            ], [
                'sent_at' => now(),
            ]);

            $created++;
        }

        return [
            'notified_members' => $created,
            'message' => $params['message'] ?? null,
        ];
    }
}
