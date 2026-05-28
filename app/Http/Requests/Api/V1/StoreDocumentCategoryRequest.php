<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DocumentCategory;
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
            'sensitivity' => ['nullable', 'string', Rule::in([
                DocumentCategory::SENSITIVITY_NORMAL,
                DocumentCategory::SENSITIVITY_SENSITIVE,
                DocumentCategory::SENSITIVITY_CONFIDENTIAL,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
