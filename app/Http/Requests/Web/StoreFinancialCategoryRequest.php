<?php

namespace App\Http\Requests\Web;

use App\Models\FinancialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([FinancialCategory::TYPE_INCOME, FinancialCategory::TYPE_EXPENSE, FinancialCategory::TYPE_BOTH])],
            'color' => ['nullable', 'string', 'max:16'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
