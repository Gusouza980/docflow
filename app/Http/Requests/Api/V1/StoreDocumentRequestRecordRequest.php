<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DocumentRequest;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequestRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DocumentRequest::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organizationId)],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.document_category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')->where('organization_id', $organizationId)],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.instructions' => ['nullable', 'string'],
            'items.*.due_at' => ['nullable', 'date'],
        ];
    }
}
