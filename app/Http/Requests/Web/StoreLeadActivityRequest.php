<?php

namespace App\Http\Requests\Web;

use App\Models\LeadActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadActivityRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(array_keys(LeadActivity::typeLabels()))],
            'body' => ['required', 'string', 'max:5000'],
            'happened_at' => ['nullable', 'date'],
        ];
    }
}
