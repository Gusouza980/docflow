<?php

namespace App\Http\Requests\Web\Platform;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationPlanOverrideRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'limits' => ['nullable', 'array'],
            'limits.max_members' => ['nullable', 'integer', 'min:-1'],
            'limits.max_clients' => ['nullable', 'integer', 'min:-1'],
            'limits.max_storage_mb' => ['nullable', 'integer', 'min:-1'],
            'limits.max_portal_accesses' => ['nullable', 'integer', 'min:-1'],
            'features' => ['nullable', 'array'],
            'features.portal' => ['nullable', 'boolean'],
            'features.finance_advanced' => ['nullable', 'boolean'],
            'features.reports_scheduling' => ['nullable', 'boolean'],
            'features.audit' => ['nullable', 'boolean'],
            'features.automations' => ['nullable', 'boolean'],
        ];
    }
}
