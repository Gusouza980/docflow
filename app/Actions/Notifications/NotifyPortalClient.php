<?php

namespace App\Actions\Notifications;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Notifications\PortalClientAlertNotification;

class NotifyPortalClient
{
    public function execute(
        Client $client,
        string $subject,
        string $message,
        ?string $actionUrl = null,
    ): void {
        $accesses = ClientPortalAccess::query()
            ->where('client_id', $client->id)
            ->where('status', ClientPortalAccess::STATUS_ACTIVE)
            ->whereNotNull('password_set_at')
            ->get();

        foreach ($accesses as $access) {
            $access->notify(new PortalClientAlertNotification(
                subject: $subject,
                message: $message,
                actionUrl: $actionUrl ?? route('portal.login', absolute: true),
            ));
        }
    }
}
