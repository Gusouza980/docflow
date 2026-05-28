<?php

namespace App\Http\Requests\Web;

use App\Models\CalendarEvent;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::in([CalendarEvent::TYPE_INTERNAL, CalendarEvent::TYPE_MEETING, CalendarEvent::TYPE_DEADLINE, CalendarEvent::TYPE_HEARING])],
            'status' => ['nullable', 'string', Rule::in([CalendarEvent::STATUS_SCHEDULED, CalendarEvent::STATUS_CONFIRMED, CalendarEvent::STATUS_CANCELLED, CalendarEvent::STATUS_DONE])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'participants' => ['nullable', 'array'],
            'participants.*.organization_member_id' => ['nullable', 'integer', Rule::exists('organization_members', 'id')->where('organization_id', $organizationId)->where('status', OrganizationMember::STATUS_ACTIVE)],
            'participants.*.external_name' => ['nullable', 'string', 'max:255'],
            'participants.*.external_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
