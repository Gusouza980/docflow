<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayableRequest extends FormRequest
{
    use ConvertsMoneyFields;

    public function authorize(): bool
    {
        return $this->user() !== null;
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

    public function rules(): array
    {
        $organizationId = $this->session()->get('active_organization_id');

        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('organization_id', $organizationId)],
            'description' => ['required', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'due_at' => ['required', 'date'],
            'competence_date' => ['nullable', 'date'],
            'is_reimbursable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
