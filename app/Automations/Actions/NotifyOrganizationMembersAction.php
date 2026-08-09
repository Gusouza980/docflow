<?php

namespace App\Automations\Actions;

use App\Models\AutomationRule;
use App\Models\InternalReminder;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

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
            ->with('user')
            ->where('organization_id', $rule->organization_id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->whereIn('role', $roles)
            ->get();

        $notified = 0;

        foreach ($members as $member) {
            $user = $member->user;

            if (! $user instanceof User) {
                continue;
            }

            if (! $this->userCanViewSubject($user, $subject)) {
                continue;
            }

            $reminder = InternalReminder::query()->updateOrCreate(
                [
                    'organization_id' => $rule->organization_id,
                    'user_id' => $member->user_id,
                    'remindable_type' => $subject->getMorphClass(),
                    'remindable_id' => $subject->getKey(),
                    'type' => InternalReminder::TYPE_AUTOMATION,
                ],
                [
                    'body' => $message,
                    'remind_at' => now(),
                    'sent_at' => now(),
                    'read_at' => null,
                ],
            );

            if ($reminder->wasRecentlyCreated || $reminder->wasChanged(['body', 'remind_at', 'read_at', 'sent_at'])) {
                $notified++;
            }
        }

        return [
            'notified_members' => $notified,
            'message' => $message,
        ];
    }

    private function userCanViewSubject(User $user, Model $subject): bool
    {
        $policy = Gate::getPolicyFor($subject);

        if ($policy === null || ! method_exists($policy, 'view')) {
            return true;
        }

        return Gate::forUser($user)->allows('view', $subject);
    }
}
