<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedReport extends Model
{
    /** @use HasFactory<\Database\Factories\GeneratedReportFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_RELEASED = 'released';

    public const TYPE_CLIENT_MONTHLY = 'client_monthly';

    protected $fillable = [
        'organization_id',
        'client_id',
        'generated_by_user_id',
        'type',
        'title',
        'status',
        'filters',
        'payload',
        'reviewed_at',
        'released_at',
        'last_viewed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'payload' => 'array',
            'reviewed_at' => 'datetime',
            'released_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ReportDelivery::class);
    }
}
