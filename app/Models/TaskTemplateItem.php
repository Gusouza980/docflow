<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Database\Factories\TaskTemplateItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateItem extends Model
{
    /** @use HasFactory<TaskTemplateItemFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'task_template_id',
        'title',
        'description',
        'due_in_days',
        'priority',
        'checklist_items',
    ];

    protected $attributes = [
        'due_in_days' => 0,
        'priority' => TaskPriority::Normal,
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'due_in_days' => 'integer',
            'checklist_items' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }
}
