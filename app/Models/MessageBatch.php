<?php

namespace App\Models;

use Database\Factories\MessageBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageBatch extends Model
{
    /** @use HasFactory<MessageBatchFactory> */
    use HasFactory;

    public const FILTER_OVERDUE = 'overdue';

    public const FILTER_DOCUMENTS_EXPIRING = 'documents_expiring';

    public const FILTER_SELECTED = 'selected';

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'message_template_id',
        'channel',
        'filter',
        'skipped_count',
    ];

    /**
     * @return list<string>
     */
    public static function filters(): array
    {
        return [
            self::FILTER_OVERDUE,
            self::FILTER_DOCUMENTS_EXPIRING,
            self::FILTER_SELECTED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function filterLabels(): array
    {
        return [
            self::FILTER_OVERDUE => 'Clientes com cobrança vencida',
            self::FILTER_DOCUMENTS_EXPIRING => 'Clientes com documento a vencer (7 dias)',
            self::FILTER_SELECTED => 'Clientes selecionados',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClientMessage::class, 'batch_id');
    }
}
