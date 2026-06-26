<?php

namespace App\Http\Requests\Web\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PortalForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
