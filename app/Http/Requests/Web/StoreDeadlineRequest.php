<?php

namespace App\Http\Requests\Web;

use App\Models\Deadline;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:64'],
            'urgency' => ['nullable', 'string', Rule::in([Deadline::URGENCY_LOW, Deadline::URGENCY_NORMAL, Deadline::URGENCY_HIGH, Deadline::URGENCY_CRITICAL])],
            'due_at' => ['required', 'date'],
            'requires_review' => ['nullable', 'boolean'],
        ];
    }
}
