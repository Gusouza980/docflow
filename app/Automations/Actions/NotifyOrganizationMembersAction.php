<?php

namespace App\Automations\Actions;

use App\Models\AutomationRule;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

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

        $message = (string) ($params['message'] ?? 'Há uma atualização automática que requer atenção.');

        $members = OrganizationMember::query()
            ->where('organization_id', $rule->organization_id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->whereIn('role', $roles)
            ->get();

        $created = 0;

        foreach ($members as $member) {
            try {
                InternalReminder::query()->firstOrCreate(
                    [
                        'organization_id' => $rule->organization_id,
                        'user_id' => $member->user_id,
                        'remindable_type' => $subject->getMorphClass(),
                        'remindable_id' => $subject->getKey(),
                        'type' => InternalReminder::TYPE_AUTOMATION,
                    ],
                    [
                        'remind_at' => now(),
                        'sent_at' => now(),
                    ],
                );
                $created++;
            } catch (QueryException) {
                // Já notificado para este subject/membro.
            }
        }

        return [
            'notified_members' => $created,
            'message' => $message,
        ];
    }
}
