<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientTagRequest extends FormRequest
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
        $organizationId = $this->session()->get('active_organization_id');

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('client_tags', 'name')->where('organization_id', $organizationId)],
            'color' => ['nullable', 'string', 'max:16'],
        ];
    }
}
