<?php

namespace App\Http\Requests\Web;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => [
                'required',
                'string',
                Rule::in(array_values(array_filter(
                    Lead::stages(),
                    fn (string $stage): bool => $stage !== Lead::STAGE_WON,
                ))),
            ],
            'lost_reason' => ['nullable', 'required_if:stage,'.Lead::STAGE_LOST, 'string', 'max:255'],
        ];
    }
}
