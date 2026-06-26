<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DocumentSensitivity;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Support\OrganizationContext;
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
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'document_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('document_categories', 'id')->where('organization_id', $organizationId)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in([
                Document::STATUS_RECEIVED,
                Document::STATUS_APPROVED,
                Document::STATUS_REJECTED,
                Document::STATUS_EXPIRED,
                Document::STATUS_REPLACED,
            ])],
            'visibility' => ['sometimes', Rule::enum(DocumentVisibility::class)],
            'sensitivity' => ['sometimes', Rule::enum(DocumentSensitivity::class)],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
