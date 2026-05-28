<?php

namespace App\Http\Requests\Web;

use App\Models\OrganizationMember;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->activeOrganizationId();

        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'assigned_to_member_id' => ['required', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in([Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_BLOCKED, Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])],
            'priority' => ['required', 'string', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_CRITICAL])],
            'due_at' => ['required', 'date'],
        ];
    }

    private function activeOrganizationId(): ?int
    {
        return $this->session()->get('active_organization_id');
    }
}
