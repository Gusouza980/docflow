<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'type' => ['required', 'string', Rule::in([Client::TYPE_INDIVIDUAL, Client::TYPE_COMPANY])],
            'display_name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:32', Rule::unique('clients', 'document_number')->where('organization_id', $organizationId)],
            'status' => ['nullable', 'string', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE, Client::STATUS_NEGOTIATION, Client::STATUS_DELINQUENT, Client::STATUS_CLOSED])],
            'priority' => ['nullable', 'string', Rule::in([Client::PRIORITY_LOW, Client::PRIORITY_NORMAL, Client::PRIORITY_HIGH])],
            'risk_level' => ['nullable', 'string', Rule::in([Client::RISK_LOW, Client::RISK_MEDIUM, Client::RISK_HIGH])],
            'potential_revenue_cents' => ['nullable', 'integer', 'min:0'],
            'origin' => ['nullable', 'string', 'max:255'],
            'access_policy' => ['nullable', 'string', Rule::in([Client::ACCESS_ALL_MEMBERS, Client::ACCESS_RESTRICTED])],
            'internal_notes' => ['nullable', 'string'],
            'entered_at' => ['nullable', 'date'],
            'responsible_member_ids' => ['required', 'array', 'min:1'],
            'responsible_member_ids.*' => ['integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'individual_profile' => ['required_if:type,'.Client::TYPE_INDIVIDUAL, 'array'],
            'individual_profile.full_name' => ['required_if:type,'.Client::TYPE_INDIVIDUAL, 'string', 'max:255'],
            'individual_profile.rg' => ['nullable', 'string', 'max:32'],
            'individual_profile.birth_date' => ['nullable', 'date'],
            'individual_profile.marital_status' => ['nullable', 'string', 'max:64'],
            'individual_profile.profession' => ['nullable', 'string', 'max:255'],
            'company_profile' => ['required_if:type,'.Client::TYPE_COMPANY, 'array'],
            'company_profile.legal_name' => ['required_if:type,'.Client::TYPE_COMPANY, 'string', 'max:255'],
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
                $document = (string) $this->input('document_number');

                if ($document === '') {
                    return;
                }

                $expectedLength = $this->input('type') === Client::TYPE_COMPANY ? 14 : 11;

                if (mb_strlen($document) !== $expectedLength) {
                    $validator->errors()->add('document_number', 'The document number length is invalid for the selected client type.');
                }
            },
        ];
    }
}
