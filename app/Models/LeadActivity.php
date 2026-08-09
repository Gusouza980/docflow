<?php

namespace App\Models;

use Database\Factories\LeadActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    /** @use HasFactory<LeadActivityFactory> */
    use HasFactory;

    public const TYPE_CALL = 'call';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_EMAIL = 'email';

    public const TYPE_NOTE = 'note';

    protected $fillable = [
        'lead_id',
        'created_by_user_id',
        'type',
        'body',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_CALL => 'Ligação',
            self::TYPE_MEETING => 'Reunião',
            self::TYPE_WHATSAPP => 'WhatsApp',
            self::TYPE_EMAIL => 'E-mail',
            self::TYPE_NOTE => 'Nota',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
