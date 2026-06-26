<?php

namespace App\Http\Requests\Web;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientHubTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('assigned_to_member_id') === '') {
            $this->merge(['assigned_to_member_id' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to_member_id' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [
                Ticket::STATUS_NEW,
                Ticket::STATUS_TRIAGE,
                Ticket::STATUS_WAITING_CLIENT,
                Ticket::STATUS_WAITING_THIRD_PARTY,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
            ])],
            'priority' => ['sometimes', 'string', 'in:low,normal,high'],
            'visible_to_client' => ['sometimes', 'boolean'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
