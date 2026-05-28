<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientMessageResource extends JsonResource
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
            'template' => $this->whenLoaded('template', fn (): ?array => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ] : null),
            'ticket_id' => $this->ticket_id,
            'channel' => $this->channel,
            'direction' => $this->direction,
            'status' => $this->status,
            'subject' => $this->subject,
            'body' => $this->body,
            'external_name' => $this->external_name,
            'external_email' => $this->external_email,
            'sent_at' => $this->sent_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
