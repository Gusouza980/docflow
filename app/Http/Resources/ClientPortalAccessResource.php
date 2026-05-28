<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPortalAccessResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toDateString(),
            'last_used_at' => $this->last_used_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
        ];
    }
}
