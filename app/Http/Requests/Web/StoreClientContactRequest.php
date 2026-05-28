<?php

namespace App\Http\Requests\Web;

use App\Models\ClientContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientContactRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', Rule::in([ClientContact::TYPE_GENERAL, ClientContact::TYPE_FINANCIAL, ClientContact::TYPE_OPERATIONAL])],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
