<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientPortalTicketFromMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('portal')->check();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
