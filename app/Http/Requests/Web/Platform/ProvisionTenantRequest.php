<?php

namespace App\Http\Requests\Web\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProvisionTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_email' => mb_strtolower((string) $this->input('owner_email')),
            'document' => $this->filled('document') ? $this->input('document') : null,
            'email' => $this->filled('email') ? $this->input('email') : null,
            'plan_id' => $this->filled('plan_id') ? $this->input('plan_id') : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:32', Rule::unique('organizations', 'document')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['nullable', 'timezone'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ];
    }
}
