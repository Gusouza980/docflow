<?php

namespace App\Http\Requests\Web;

use App\Enums\DocumentSensitivity;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
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
            'status' => ['required', 'string', Rule::in([Document::STATUS_RECEIVED, Document::STATUS_APPROVED, Document::STATUS_REJECTED, Document::STATUS_EXPIRED, Document::STATUS_REPLACED])],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
            'sensitivity' => ['required', Rule::enum(DocumentSensitivity::class)],
            'expires_at' => ['nullable', 'date'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function activeOrganizationId(): ?int
    {
        return $this->session()->get('active_organization_id');
    }
}
