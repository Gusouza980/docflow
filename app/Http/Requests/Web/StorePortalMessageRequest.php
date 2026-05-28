<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePortalMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'message_template_id' => ['nullable', 'integer'],
            'channel' => ['required', 'string', 'in:email,whatsapp,phone,portal'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required_without:message_template_id', 'nullable', 'string', 'max:5000'],
            'create_ticket' => ['boolean'],
        ];
    }
}
