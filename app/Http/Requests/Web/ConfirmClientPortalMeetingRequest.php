<?php

namespace App\Http\Requests\Web;

use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmClientPortalMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('portal')->check();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in([
                CalendarEvent::PORTAL_CONFIRMATION_CONFIRMED,
                CalendarEvent::PORTAL_CONFIRMATION_DECLINED,
                CalendarEvent::PORTAL_CONFIRMATION_RESCHEDULE_REQUESTED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000', Rule::requiredIf(
                $this->input('action') === CalendarEvent::PORTAL_CONFIRMATION_RESCHEDULE_REQUESTED
            )],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Informe uma sugestão de horário ou motivo para solicitar remarcação.',
        ];
    }
}
