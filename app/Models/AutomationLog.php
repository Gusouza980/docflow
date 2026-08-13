<?php

namespace App\Models;

use Database\Factories\AutomationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AutomationLog extends Model
{
    /** @use HasFactory<AutomationLogFactory> */
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'organization_id',
        'automation_rule_id',
        'trigger',
        'subject_type',
        'subject_id',
        'dedupe_key',
        'status',
        'result',
        'ran_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'ran_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
