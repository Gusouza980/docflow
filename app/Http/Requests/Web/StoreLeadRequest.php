<?php

namespace App\Http\Requests\Web;

use App\Models\Lead;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'origin' => ['nullable', 'string', Rule::in(array_keys(Lead::originLabels()))],
            'stage' => ['nullable', 'string', Rule::in(Lead::stages())],
            'estimated_value_cents' => ['nullable', 'integer', 'min:0'],
            'service_interest' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_members', 'user_id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', OrganizationMember::STATUS_ACTIVE)),
            ],
        ];
    }
}
