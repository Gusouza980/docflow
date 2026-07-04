<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', 'in:overview,productivity,documents,finance'],
            'filters' => ['nullable', 'array'],
            'filters.start_date' => ['nullable', 'date'],
            'filters.end_date' => ['nullable', 'date'],
            'filters.client_id' => ['nullable', 'integer'],
            'filters.status' => ['nullable', 'string'],
        ];
    }
}
