<?php

namespace App\Http\Requests\Web\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationPlatformNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
