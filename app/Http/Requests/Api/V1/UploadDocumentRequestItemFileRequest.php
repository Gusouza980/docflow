<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DocumentVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequestItemFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('item')->documentRequest) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source' => ['nullable', 'string', Rule::in([
                DocumentVersion::SOURCE_INTERNAL,
                DocumentVersion::SOURCE_PORTAL,
                DocumentVersion::SOURCE_EMAIL,
                DocumentVersion::SOURCE_WHATSAPP,
                DocumentVersion::SOURCE_IMPORT,
            ])],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }
}
