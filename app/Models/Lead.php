<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const STAGE_NEW = 'new';

    public const STAGE_FIRST_CONTACT = 'first_contact';

    public const STAGE_DIAGNOSIS = 'diagnosis';

    public const STAGE_PROPOSAL = 'proposal';

    public const STAGE_NEGOTIATION = 'negotiation';

    public const STAGE_WON = 'won';

    public const STAGE_LOST = 'lost';

    public const STAGE_NO_RESPONSE = 'no_response';

    public const ORIGIN_REFERRAL = 'referral';

    public const ORIGIN_WEBSITE = 'website';

    public const ORIGIN_INSTAGRAM = 'instagram';

    public const ORIGIN_GOOGLE = 'google';

    public const ORIGIN_EVENT = 'event';

    public const ORIGIN_CURRENT_CLIENT = 'current_client';

    public const ORIGIN_PARTNER = 'partner';

    public const ORIGIN_PAID_ADS = 'paid_ads';

    public const ORIGIN_PHONE = 'phone';

    public const ORIGIN_WHATSAPP = 'whatsapp';

    public const ORIGIN_OTHER = 'other';

    protected $fillable = [
        'organization_id',
        'client_id',
        'owner_user_id',
        'name',
        'email',
        'phone',
        'origin',
        'stage',
        'estimated_value_cents',
        'service_interest',
        'lost_reason',
        'converted_at',
        'metadata',
    ];

    protected $attributes = [
        'stage' => self::STAGE_NEW,
    ];

    protected function casts(): array
    {
        return [
            'estimated_value_cents' => 'integer',
            'converted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function stages(): array
    {
        return [
            self::STAGE_NEW,
            self::STAGE_FIRST_CONTACT,
            self::STAGE_DIAGNOSIS,
            self::STAGE_PROPOSAL,
            self::STAGE_NEGOTIATION,
            self::STAGE_WON,
            self::STAGE_LOST,
            self::STAGE_NO_RESPONSE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function stageLabels(): array
    {
        return [
            self::STAGE_NEW => 'Novo contato',
            self::STAGE_FIRST_CONTACT => 'Primeiro atendimento',
            self::STAGE_DIAGNOSIS => 'Diagnóstico',
            self::STAGE_PROPOSAL => 'Proposta',
            self::STAGE_NEGOTIATION => 'Negociação',
            self::STAGE_WON => 'Aceito',
            self::STAGE_LOST => 'Perdido',
            self::STAGE_NO_RESPONSE => 'Sem resposta',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function originLabels(): array
    {
        return [
            self::ORIGIN_REFERRAL => 'Indicação',
            self::ORIGIN_WEBSITE => 'Site',
            self::ORIGIN_INSTAGRAM => 'Instagram',
            self::ORIGIN_GOOGLE => 'Google',
            self::ORIGIN_EVENT => 'Evento',
            self::ORIGIN_CURRENT_CLIENT => 'Cliente atual',
            self::ORIGIN_PARTNER => 'Parceiro',
            self::ORIGIN_PAID_ADS => 'Tráfego pago',
            self::ORIGIN_PHONE => 'Ligação',
            self::ORIGIN_WHATSAPP => 'WhatsApp',
            self::ORIGIN_OTHER => 'Outro',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function isConverted(): bool
    {
        return $this->client_id !== null || $this->converted_at !== null;
    }

    public function stageLabel(): string
    {
        return self::stageLabels()[$this->stage] ?? $this->stage;
    }
}
