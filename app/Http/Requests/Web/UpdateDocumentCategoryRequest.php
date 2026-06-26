<?php

namespace App\Http\Requests\Web;

use App\Enums\DocumentSensitivity;
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
            'sensitivity' => ['required', Rule::enum(DocumentSensitivity::class)],
            'is_active' => ['boolean'],
        ];
    }
}
