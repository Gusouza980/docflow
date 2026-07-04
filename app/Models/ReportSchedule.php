<?php

namespace App\Models;

use Database\Factories\ReportScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSchedule extends Model
{
    /** @use HasFactory<ReportScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'client_id',
        'name',
        'report_type',
        'frequency',
        'filters',
        'is_active',
        'next_run_at',
        'last_run_at',
        'last_error',
        'consecutive_failures',
    ];

    protected $attributes = [
        'frequency' => 'monthly',
        'is_active' => true,
        'consecutive_failures' => 0,
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_active' => 'boolean',
            'next_run_at' => 'date',
            'last_run_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }
}
