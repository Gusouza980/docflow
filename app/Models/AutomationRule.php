<?php

namespace App\Models;

use Database\Factories\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory;

    public const TRIGGER_CLIENT_CREATED = 'client.created';

    public const TRIGGER_DOCUMENT_EXPIRING = 'document.expiring';

    public const TRIGGER_RECEIVABLE_OVERDUE = 'receivable.overdue';

    public const TRIGGER_CONTRACT_EXPIRING = 'contract.expiring';

    public const TRIGGER_LEAD_STAGE_CHANGED = 'lead.stage_changed';

    public const ACTION_CREATE_TASKS_FROM_TEMPLATE = 'create_tasks_from_template';

    public const ACTION_CREATE_DOCUMENT_REQUEST = 'create_document_request';

    public const ACTION_NOTIFY_ORGANIZATION_MEMBERS = 'notify_organization_members';

    public const ACTION_SEND_MESSAGE_TEMPLATE = 'send_message_template';

    protected $fillable = [
        'organization_id',
        'name',
        'trigger',
        'preset_key',
        'conditions',
        'actions',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function triggers(): array
    {
        return [
            self::TRIGGER_CLIENT_CREATED,
            self::TRIGGER_DOCUMENT_EXPIRING,
            self::TRIGGER_RECEIVABLE_OVERDUE,
            self::TRIGGER_CONTRACT_EXPIRING,
            self::TRIGGER_LEAD_STAGE_CHANGED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return [
            self::TRIGGER_CLIENT_CREATED => 'Cliente criado',
            self::TRIGGER_DOCUMENT_EXPIRING => 'Documento a vencer',
            self::TRIGGER_RECEIVABLE_OVERDUE => 'Cobrança vencida',
            self::TRIGGER_CONTRACT_EXPIRING => 'Contrato a vencer',
            self::TRIGGER_LEAD_STAGE_CHANGED => 'Lead mudou de etapa',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }
}
