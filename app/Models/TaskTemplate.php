<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Database\Factories\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskTemplate extends Model
{
    /** @use HasFactory<TaskTemplateFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'priority',
        'is_active',
    ];

    protected $attributes = [
        'priority' => TaskPriority::Normal,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class);
    }
}
