<?php

namespace App\Http\Requests\Api\V1;

use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientTagRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('client_tags', 'name')->where('organization_id', $organizationId)],
            'color' => ['nullable', 'string', 'max:16'],
        ];
    }
}
