<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsMoneyFields;
use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Proposal::statusLabels()))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
