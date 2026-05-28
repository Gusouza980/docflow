<?php

namespace App\Http\Requests\Web;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTasksFromTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('template')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->session()->get('active_organization_id');

        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'assigned_to_member_id' => ['required', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'base_date' => ['nullable', 'date'],
        ];
    }
}
