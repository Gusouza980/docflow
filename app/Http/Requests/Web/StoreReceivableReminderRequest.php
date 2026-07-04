<?php

namespace App\Http\Requests\Web;

use App\Models\ReceivableReminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivableReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in([
                ReceivableReminder::CHANNEL_EMAIL,
                ReceivableReminder::CHANNEL_WHATSAPP,
                ReceivableReminder::CHANNEL_PHONE,
                ReceivableReminder::CHANNEL_INTERNAL,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'notify_client' => ['boolean'],
        ];
    }
}
