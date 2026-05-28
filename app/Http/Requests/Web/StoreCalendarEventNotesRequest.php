<?php

namespace App\Http\Requests\Web;

use App\Models\OrganizationMember;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->route('event')->organization_id;

        return [
            'notes' => ['required', 'string'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.title' => ['required_with:tasks', 'string', 'max:255'],
            'tasks.*.assigned_to_member_id' => ['required_with:tasks', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'tasks.*.due_at' => ['required_with:tasks', 'date'],
            'tasks.*.priority' => ['nullable', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_CRITICAL])],
        ];
    }
}
