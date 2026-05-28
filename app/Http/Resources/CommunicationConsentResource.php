<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationConsentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $this->client->id,
                'name' => $this->client->display_name,
            ]),
            'channel' => $this->channel,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'source' => $this->source,
            'granted_at' => $this->granted_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'notes' => $this->notes,
        ];
    }
}
