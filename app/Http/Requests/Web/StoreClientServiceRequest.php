<?php

namespace App\Http\Requests\Web;

use App\Models\ClientService;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(WebOrganizationContext::class)->membership($this)?->organization_id;

        return [
            'service_type_id' => [
                'required',
                'integer',
                Rule::exists('service_types', 'id')->where('organization_id', $organizationId),
            ],
            'status' => ['nullable', 'string', Rule::in(ClientService::statuses())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'assigned_to_member_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_members', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', OrganizationMember::STATUS_ACTIVE)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
