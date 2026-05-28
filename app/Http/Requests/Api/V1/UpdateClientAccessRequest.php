<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('client')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'access_policy' => ['required', 'string', Rule::in([Client::ACCESS_ALL_MEMBERS, Client::ACCESS_RESTRICTED])],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
        ];
    }
}
