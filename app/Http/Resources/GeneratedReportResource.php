<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedReportResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'client' => $this->whenLoaded('client', fn (): ?array => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->display_name,
            ] : null),
            'filters' => $this->filters ?? [],
            'payload' => $this->payload ?? [],
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'last_viewed_at' => $this->last_viewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
