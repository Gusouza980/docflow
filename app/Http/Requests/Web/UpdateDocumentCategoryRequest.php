<?php

namespace App\Http\Requests\Web;

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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sensitivity' => ['required', 'string', Rule::in([DocumentCategory::SENSITIVITY_NORMAL, DocumentCategory::SENSITIVITY_SENSITIVE, DocumentCategory::SENSITIVITY_CONFIDENTIAL])],
            'is_active' => ['boolean'],
        ];
    }
}
