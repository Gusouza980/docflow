<?php

namespace App\Http\Requests\Web;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([
                OrganizationMember::ROLE_ADMIN,
                OrganizationMember::ROLE_MANAGER,
                OrganizationMember::ROLE_PROFESSIONAL,
                OrganizationMember::ROLE_ASSISTANT,
                OrganizationMember::ROLE_FINANCE,
                OrganizationMember::ROLE_READONLY,
            ])],
        ];
    }
}
