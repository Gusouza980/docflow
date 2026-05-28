<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportScheduleResource extends JsonResource
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
            'name' => $this->name,
            'report_type' => $this->report_type,
            'frequency' => $this->frequency,
            'client' => $this->whenLoaded('client', fn (): ?array => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->display_name,
            ] : null),
            'filters' => $this->filters ?? [],
            'is_active' => $this->is_active,
            'next_run_at' => $this->next_run_at?->toDateString(),
            'last_run_at' => $this->last_run_at?->toISOString(),
        ];
    }
}
