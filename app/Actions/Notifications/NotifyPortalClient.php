<?php

namespace App\Actions\Notifications;

use App\Contracts\Mail\TransactionalMailer;
use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\PortalClientAlert;
use App\Notifications\PortalClientAlertNotification;

class NotifyPortalClient
{
    public function __construct(
        private TransactionalMailer $transactionalMailer,
    ) {}

    public function execute(
        Client $client,
        string $subject,
        string $message,
        ?string $actionUrl = null,
        string $type = PortalClientAlert::TYPE_GENERAL,
    ): void {
        $actionUrl ??= route('client-portal.dashboard', absolute: true);

        $accesses = ClientPortalAccess::query()
            ->where('client_id', $client->id)
            ->where('status', ClientPortalAccess::STATUS_ACTIVE)
            ->whereNotNull('password_set_at')
            ->get();

        foreach ($accesses as $access) {
            PortalClientAlert::create([
                'organization_id' => $access->organization_id,
                'client_id' => $client->id,
                'client_portal_access_id' => $access->id,
                'type' => $type,
                'title' => $subject,
                'message' => $message,
                'action_url' => $actionUrl,
            ]);

            $this->transactionalMailer->notify($access, new PortalClientAlertNotification(
                subject: $subject,
                message: $message,
                actionUrl: $actionUrl,
            ));
        }
    }
}
