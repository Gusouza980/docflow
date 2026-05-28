<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'type' => $this->type,
            'display_name' => $this->display_name,
            'document_number' => $canViewSensitive ? $this->document_number : $this->maskedDocument(),
            'status' => $this->status,
            'priority' => $this->priority,
            'risk_level' => $this->risk_level,
            'potential_revenue_cents' => $canViewSensitive ? $this->potential_revenue_cents : null,
            'origin' => $this->origin,
            'access_policy' => $this->access_policy,
            'internal_notes' => $canViewSensitive ? $this->internal_notes : null,
            'entered_at' => $this->entered_at?->toDateString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'closure_reason' => $this->closure_reason,
            'primary_responsible' => $this->whenLoaded('primaryResponsible', fn () => [
                'id' => $this->primaryResponsible?->id,
                'user' => [
                    'id' => $this->primaryResponsible?->user?->id,
                    'name' => $this->primaryResponsible?->user?->name,
                    'email' => $this->primaryResponsible?->user?->email,
                ],
            ]),
            'individual_profile' => $this->whenLoaded('individualProfile'),
            'company_profile' => $this->whenLoaded('companyProfile'),
            'contacts' => ClientContactResource::collection($this->whenLoaded('contacts')),
            'tags' => ClientTagResource::collection($this->whenLoaded('tags')),
            'responsibles' => $this->whenLoaded('responsibles', fn () => $this->responsibles->map(fn ($responsible) => [
                'id' => $responsible->id,
                'is_primary' => (bool) $responsible->pivot->is_primary,
                'user' => [
                    'id' => $responsible->user?->id,
                    'name' => $responsible->user?->name,
                    'email' => $responsible->user?->email,
                ],
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function maskedDocument(): ?string
    {
        if (! $this->document_number) {
            return null;
        }

        return str_repeat('*', max(mb_strlen($this->document_number) - 4, 0)).mb_substr($this->document_number, -4);
    }
}
