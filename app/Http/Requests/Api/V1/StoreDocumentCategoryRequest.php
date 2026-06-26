<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DocumentSensitivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DocumentCategory::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sensitivity' => ['nullable', Rule::enum(DocumentSensitivity::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
