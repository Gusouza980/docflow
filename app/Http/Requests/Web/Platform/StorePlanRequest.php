<?php

namespace App\Http\Requests\Web\Platform;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('plans', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'billing_interval' => ['required', 'string', Rule::in([Plan::BILLING_INTERVAL_MONTH, Plan::BILLING_INTERVAL_YEAR])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'limits' => ['required', 'array'],
            'limits.max_members' => ['required', 'integer', 'min:-1'],
            'limits.max_clients' => ['required', 'integer', 'min:-1'],
            'limits.max_storage_mb' => ['required', 'integer', 'min:-1'],
            'limits.max_portal_accesses' => ['required', 'integer', 'min:-1'],
            'features' => ['required', 'array'],
            'features.portal' => ['boolean'],
            'features.finance_advanced' => ['boolean'],
            'features.reports_scheduling' => ['boolean'],
            'features.audit' => ['boolean'],
            'features.automations' => ['boolean'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:255'],
        ];
    }
}
