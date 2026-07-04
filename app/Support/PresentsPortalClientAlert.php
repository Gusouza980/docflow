<?php

namespace App\Support;

use App\Models\PortalClientAlert;

class PresentsPortalClientAlert
{
    /**
     * @return array<string, mixed>
     */
    public function present(PortalClientAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'title' => $alert->title,
            'body' => $alert->message,
            'url' => $alert->action_url,
            'read_at' => $alert->read_at?->toISOString(),
            'is_read' => $alert->read_at !== null,
            'created_at' => $alert->created_at?->toISOString(),
        ];
    }
}
