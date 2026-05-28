<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentRequestItem extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentRequestItemFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'document_request_id',
        'document_category_id',
        'document_id',
        'title',
        'instructions',
        'due_at',
        'status',
        'received_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_REQUESTED,
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'received_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
