<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedReportFilterResource extends JsonResource
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
            'filters' => $this->filters ?? [],
            'is_shared' => $this->is_shared,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
