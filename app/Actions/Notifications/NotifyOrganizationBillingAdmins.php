<?php

namespace App\Actions\Notifications;

use App\Contracts\Mail\TransactionalMailer;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Notifications\Notification;

class NotifyOrganizationBillingAdmins
{
    public function __construct(private TransactionalMailer $transactionalMailer) {}

    public function execute(Organization $organization, Notification $notification): void
    {
        $admins = OrganizationMember::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->where('role', OrganizationMember::ROLE_ADMIN)
            ->get();

        foreach ($admins as $admin) {
            if ($admin->user !== null) {
                $this->transactionalMailer->notify($admin->user, $notification);
            }
        }
    }
}
