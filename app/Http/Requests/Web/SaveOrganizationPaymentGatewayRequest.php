<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class SaveOrganizationPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'max:500'],
            'webhook_token' => ['nullable', 'string', 'max:500'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
