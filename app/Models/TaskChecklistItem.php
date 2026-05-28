<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\TaskChecklistItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'task_id',
        'title',
        'is_required',
        'is_completed',
        'completed_at',
    ];

    protected $attributes = [
        'is_required' => false,
        'is_completed' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
