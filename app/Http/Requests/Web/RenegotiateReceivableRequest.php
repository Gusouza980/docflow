<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class RenegotiateReceivableRequest extends FormRequest
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
        return [
            'renegotiation_reason' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'due_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
