<?php

namespace App\Models;

use Database\Factories\ReceivableReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableReminder extends Model
{
    /** @use HasFactory<ReceivableReminderFactory> */
    use HasFactory;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PHONE = 'phone';

    public const CHANNEL_INTERNAL = 'internal';

    protected $fillable = [
        'organization_id',
        'receivable_id',
        'sent_by_user_id',
        'channel',
        'notes',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
