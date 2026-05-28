<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientMessageRequest extends FormRequest
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
            'ticket_id' => ['nullable', 'integer'],
            'channel' => ['required', 'string', 'in:email,whatsapp,phone,portal'],
            'direction' => ['required', 'string', 'in:outbound,inbound'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required_without:message_template_id', 'nullable', 'string', 'max:5000'],
            'external_name' => ['nullable', 'string', 'max:255'],
            'external_email' => ['nullable', 'email', 'max:255'],
            'create_ticket' => ['boolean'],
        ];
    }
}
