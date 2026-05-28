<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('category')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'validity_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'sensitivity' => ['sometimes', 'string', Rule::in([
                DocumentCategory::SENSITIVITY_NORMAL,
                DocumentCategory::SENSITIVITY_SENSITIVE,
                DocumentCategory::SENSITIVITY_CONFIDENTIAL,
            ])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
