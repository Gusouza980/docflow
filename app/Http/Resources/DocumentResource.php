<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $request->attributes->get('organization_member');
        $canViewSensitive = $membership?->role !== \App\Models\OrganizationMember::ROLE_READONLY;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'client_id' => $this->client_id,
            'document_category_id' => $this->document_category_id,
            'title' => $this->title,
            'description' => $canViewSensitive ? $this->description : null,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'sensitivity' => $canViewSensitive ? $this->sensitivity : null,
            'expires_at' => $this->expires_at?->toDateString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $canViewSensitive ? $this->rejection_reason : null,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'display_name' => $this->client?->display_name,
            ]),
            'category' => new DocumentCategoryResource($this->whenLoaded('category')),
            'latest_version' => new DocumentVersionResource($this->whenLoaded('latestVersion')),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
