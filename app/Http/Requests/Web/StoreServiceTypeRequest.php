<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceTypeRequest extends FormRequest
{
    use ConvertsMoneyFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareMoneyFields();

        $raw = $this->input('monthly_document_items');

        if (is_string($raw)) {
            $this->merge([
                'monthly_document_items' => collect(preg_split('/\R/', $raw) ?: [])
                    ->map(fn (string $line): string => trim($line))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function moneyFields(): array
    {
        return ['default_amount_cents'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'default_amount_cents' => ['nullable', 'integer', 'min:0'],
            'default_billing_interval' => ['nullable', 'string', Rule::in(ServiceType::billingIntervals())],
            'monthly_document_items' => ['nullable', 'array', 'max:20'],
            'monthly_document_items.*' => ['required', 'string', 'max:255'],
        ];
    }
}
