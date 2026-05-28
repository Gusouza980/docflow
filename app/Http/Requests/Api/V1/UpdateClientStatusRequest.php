<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('client')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE, Client::STATUS_NEGOTIATION, Client::STATUS_DELINQUENT, Client::STATUS_CLOSED])],
            'closure_reason' => ['required_if:status,'.Client::STATUS_CLOSED, 'nullable', 'string', 'max:255'],
        ];
    }
}
