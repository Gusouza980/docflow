<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDelivery extends Model
{
    /** @use HasFactory<\Database\Factories\ReportDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'generated_report_id',
        'client_portal_access_id',
        'channel',
        'status',
        'delivered_at',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function generatedReport(): BelongsTo
    {
        return $this->belongsTo(GeneratedReport::class);
    }

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }
}
