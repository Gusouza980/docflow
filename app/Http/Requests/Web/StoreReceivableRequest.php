<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $organizationId = $this->session()->get('active_organization_id');

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('organization_id', $organizationId)],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'due_at' => ['required', 'date'],
            'competence_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
