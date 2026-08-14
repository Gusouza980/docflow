<?php

namespace App\Http\Requests\Web;

use App\Models\MessageBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewMessageBatchRequest extends FormRequest
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
            'filter' => ['nullable', 'string', Rule::in(MessageBatch::filters())],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'client_ids' => ['nullable', 'array', 'max:100'],
            'client_ids.*' => ['integer'],
        ];
    }
}
