<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentVersionResource extends JsonResource
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
            'document_id' => $this->document_id,
            'version_number' => $this->version_number,
            'source' => $this->source,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'hash' => $this->hash,
            'replaced_at' => $this->replaced_at?->toISOString(),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => [
                'id' => $this->uploadedBy?->id,
                'name' => $this->uploadedBy?->name,
                'email' => $this->uploadedBy?->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
