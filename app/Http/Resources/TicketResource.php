<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
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
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->user?->name,
            ] : null),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'visible_to_client' => $this->visible_to_client,
            'due_at' => $this->due_at?->toDateString(),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message): array => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'body' => $message->body,
                'visible_to_client' => $message->visible_to_client,
                'created_at' => $message->created_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
