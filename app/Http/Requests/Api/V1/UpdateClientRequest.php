<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
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
        /** @var Client $client */
        $client = $this->route('client');
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('clients', 'document_number')->where('organization_id', $organizationId)->ignore($client)],
            'priority' => ['sometimes', 'string', Rule::in([Client::PRIORITY_LOW, Client::PRIORITY_NORMAL, Client::PRIORITY_HIGH])],
            'risk_level' => ['sometimes', 'string', Rule::in([Client::RISK_LOW, Client::RISK_MEDIUM, Client::RISK_HIGH])],
            'potential_revenue_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'access_policy' => ['sometimes', 'string', Rule::in([Client::ACCESS_ALL_MEMBERS, Client::ACCESS_RESTRICTED])],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'entered_at' => ['sometimes', 'nullable', 'date'],
            'responsible_member_ids' => ['sometimes', 'array', 'min:1'],
            'responsible_member_ids.*' => ['integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'individual_profile' => ['sometimes', 'array'],
            'individual_profile.full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'individual_profile.rg' => ['nullable', 'string', 'max:32'],
            'individual_profile.birth_date' => ['nullable', 'date'],
            'individual_profile.marital_status' => ['nullable', 'string', 'max:64'],
            'individual_profile.profession' => ['nullable', 'string', 'max:255'],
            'company_profile' => ['sometimes', 'array'],
            'company_profile.legal_name' => ['sometimes', 'required', 'string', 'max:255'],
            'company_profile.trade_name' => ['nullable', 'string', 'max:255'],
            'company_profile.state_registration' => ['nullable', 'string', 'max:64'],
            'company_profile.municipal_registration' => ['nullable', 'string', 'max:64'],
            'company_profile.tax_regime' => ['nullable', 'string', 'max:64'],
            'company_profile.main_cnae' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('document_number')) {
            $this->merge([
                'document_number' => preg_replace('/\D+/', '', (string) $this->input('document_number')),
            ]);
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('document_number')) {
                    return;
                }

                /** @var Client $client */
                $client = $this->route('client');
                $expectedLength = $client->type === Client::TYPE_COMPANY ? 14 : 11;

                if (mb_strlen((string) $this->input('document_number')) !== $expectedLength) {
                    $validator->errors()->add('document_number', 'The document number length is invalid for this client type.');
                }
            },
        ];
    }
}
