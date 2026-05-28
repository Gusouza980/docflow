<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationConsent extends Model
{
    /** @use HasFactory<\Database\Factories\CommunicationConsentFactory> */
    use HasFactory;

    public const STATUS_GRANTED = 'granted';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'organization_id',
        'client_id',
        'recorded_by_user_id',
        'channel',
        'purpose',
        'status',
        'source',
        'granted_at',
        'revoked_at',
        'notes',
    ];

    protected $attributes = [
        'purpose' => 'general',
        'status' => self::STATUS_GRANTED,
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function revoke(): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
