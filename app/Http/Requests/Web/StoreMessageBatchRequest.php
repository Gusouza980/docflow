<?php

namespace App\Http\Requests\Web;

use App\Models\MessageBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageBatchRequest extends FormRequest
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
            'filter' => ['required', 'string', Rule::in(MessageBatch::filters())],
            'message_template_id' => ['required', 'integer', 'exists:message_templates,id'],
            'client_ids' => ['required_if:filter,'.MessageBatch::FILTER_SELECTED, 'nullable', 'array', 'max:100'],
            'client_ids.*' => ['integer'],
        ];
    }
}
