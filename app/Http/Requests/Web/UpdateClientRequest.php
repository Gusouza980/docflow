<?php

namespace App\Http\Requests\Web;

use App\Models\Client;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('client')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');
        $organizationId = $this->session()->get('active_organization_id');

        return [
            'display_name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:32', Rule::unique('clients', 'document_number')->where('organization_id', $organizationId)->ignore($client)],
            'priority' => ['required', 'string', Rule::in([Client::PRIORITY_LOW, Client::PRIORITY_NORMAL, Client::PRIORITY_HIGH])],
            'risk_level' => ['required', 'string', Rule::in([Client::RISK_LOW, Client::RISK_MEDIUM, Client::RISK_HIGH])],
            'potential_revenue_cents' => ['nullable', 'integer', 'min:0'],
            'origin' => ['nullable', 'string', 'max:255'],
            'access_policy' => ['required', 'string', Rule::in([Client::ACCESS_ALL_MEMBERS, Client::ACCESS_RESTRICTED])],
            'internal_notes' => ['nullable', 'string'],
            'entered_at' => ['nullable', 'date'],
            'responsible_member_ids' => ['required', 'array', 'min:1'],
            'responsible_member_ids.*' => ['integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'individual_profile' => [$client->type === Client::TYPE_INDIVIDUAL ? 'required' : 'nullable', 'array'],
            'individual_profile.full_name' => [$client->type === Client::TYPE_INDIVIDUAL ? 'required' : 'nullable', 'string', 'max:255'],
            'individual_profile.rg' => ['nullable', 'string', 'max:32'],
            'individual_profile.birth_date' => ['nullable', 'date'],
            'individual_profile.marital_status' => ['nullable', 'string', 'max:64'],
            'individual_profile.profession' => ['nullable', 'string', 'max:255'],
            'company_profile' => [$client->type === Client::TYPE_COMPANY ? 'required' : 'nullable', 'array'],
            'company_profile.legal_name' => [$client->type === Client::TYPE_COMPANY ? 'required' : 'nullable', 'string', 'max:255'],
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
                    $validator->errors()->add('document_number', 'O documento não tem o tamanho esperado para este tipo de cliente.');
                }
            },
        ];
    }
}
