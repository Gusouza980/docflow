<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentRequestItemResource extends JsonResource
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
            'document_request_id' => $this->document_request_id,
            'document_category_id' => $this->document_category_id,
            'document_id' => $this->document_id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'due_at' => $this->due_at?->toDateString(),
            'status' => $this->status,
            'received_at' => $this->received_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'category' => new DocumentCategoryResource($this->whenLoaded('category')),
            'document' => new DocumentResource($this->whenLoaded('document')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
