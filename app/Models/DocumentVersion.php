<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentVersionFactory> */
    use HasFactory;

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_EMAIL = 'email';

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'organization_id',
        'document_id',
        'uploaded_by_user_id',
        'version_number',
        'source',
        'disk',
        'path',
        'original_name',
        'stored_name',
        'mime_type',
        'size',
        'hash',
        'replaced_at',
    ];

    protected $attributes = [
        'source' => self::SOURCE_INTERNAL,
        'disk' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'size' => 'integer',
            'replaced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
