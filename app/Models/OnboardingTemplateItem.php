<?php

namespace App\Models;

use Database\Factories\OnboardingTemplateItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTemplateItem extends Model
{
    /** @use HasFactory<OnboardingTemplateItemFactory> */
    use HasFactory;

    protected $fillable = [
        'onboarding_template_id',
        'title',
        'description',
        'due_in_days',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_in_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}
