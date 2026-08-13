<?php

namespace App\Http\Requests\Web;

use App\Automations\AutomationPresets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preset_key' => ['required', 'string', Rule::in(array_keys(AutomationPresets::all()))],
            'name' => ['nullable', 'string', 'max:255'],
            'task_template_id' => ['nullable', 'integer', 'exists:task_templates,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
