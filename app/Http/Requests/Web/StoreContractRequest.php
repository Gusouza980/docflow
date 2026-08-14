<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Contract;
use App\Support\WebOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    use ConvertsMoneyFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareMoneyFields();
    }

    /**
     * @return list<string>
     */
    protected function moneyFields(): array
    {
        return ['amount_cents'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(WebOrganizationContext::class)->membership($this)?->organization_id;

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('organization_id', $organizationId),
            ],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('contracts', 'code')->where('organization_id', $organizationId),
            ],
            'status' => ['nullable', 'string', Rule::in([Contract::STATUS_DRAFT, Contract::STATUS_ACTIVE])],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'billing_interval' => ['required', 'string', Rule::in(Contract::billingIntervals())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'auto_renew' => ['nullable', 'boolean'],
            'scope_included' => ['nullable', 'string', 'max:5000'],
            'scope_excluded' => ['nullable', 'string', 'max:5000'],
            'client_service_ids' => ['nullable', 'array'],
            'client_service_ids.*' => [
                'integer',
                Rule::exists('client_services', 'id')->where('organization_id', $organizationId),
            ],
            'create_receivable_recurrence' => ['sometimes', 'boolean'],
        ];
    }
}
