<?php

namespace App\Http\Requests\Web;

use App\Models\ReceivableRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivableRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = $this->session()->get('active_organization_id');

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('organization_id', $organizationId)],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'billing_day' => ['required', 'integer', 'min:1', 'max:28'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'frequency' => ['required', 'string', Rule::in([ReceivableRecurrence::FREQUENCY_MONTHLY])],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
