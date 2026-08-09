<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceivablePaymentRequest extends FormRequest
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
        return [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
