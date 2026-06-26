<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientHubMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message_template_id' => ['nullable', 'integer'],
            'channel' => ['required', 'string', 'in:email,whatsapp,phone,portal'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required_without:message_template_id', 'nullable', 'string', 'max:5000'],
        ];
    }
}
