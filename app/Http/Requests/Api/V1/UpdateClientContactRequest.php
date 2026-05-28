<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ClientContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contact')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:32'],
            'type' => ['sometimes', 'string', Rule::in([ClientContact::TYPE_GENERAL, ClientContact::TYPE_FINANCIAL, ClientContact::TYPE_OPERATIONAL])],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
