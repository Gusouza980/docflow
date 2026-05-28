<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\MessageTemplateFactory> */
    use HasFactory;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PHONE = 'phone';

    public const CHANNEL_PORTAL = 'portal';

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'name',
        'channel',
        'purpose',
        'subject',
        'body',
        'variables',
        'requires_consent',
        'is_active',
    ];

    protected $attributes = [
        'channel' => self::CHANNEL_EMAIL,
        'purpose' => 'general',
        'requires_consent' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'requires_consent' => 'boolean',
            'is_active' => 'boolean',
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

    /**
     * @param  array<string, string>  $variables
     */
    public function renderBody(array $variables): string
    {
        return str($this->body)
            ->replaceMatches('/{{\s*([a-zA-Z0-9_]+)\s*}}/', fn (array $matches): string => $variables[$matches[1]] ?? $matches[0])
            ->toString();
    }
}
