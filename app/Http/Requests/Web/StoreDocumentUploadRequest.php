<?php

namespace App\Http\Requests\Web;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->activeOrganizationId();

        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'document_category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')->where('organization_id', $organizationId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in([Document::STATUS_RECEIVED, Document::STATUS_APPROVED, Document::STATUS_REJECTED])],
            'visibility' => ['nullable', 'string', Rule::in([Document::VISIBILITY_INTERNAL, Document::VISIBILITY_CLIENT, Document::VISIBILITY_RESTRICTED, Document::VISIBILITY_CONFIDENTIAL])],
            'sensitivity' => ['nullable', 'string', Rule::in([Document::SENSITIVITY_NORMAL, Document::SENSITIVITY_SENSITIVE, Document::SENSITIVITY_CONFIDENTIAL])],
            'expires_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', Rule::in([DocumentVersion::SOURCE_INTERNAL, DocumentVersion::SOURCE_PORTAL, DocumentVersion::SOURCE_EMAIL, DocumentVersion::SOURCE_WHATSAPP, DocumentVersion::SOURCE_IMPORT])],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    private function activeOrganizationId(): ?int
    {
        return $this->session()->get('active_organization_id');
    }
}
