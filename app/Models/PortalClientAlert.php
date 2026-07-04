<?php

namespace App\Models;

use Database\Factories\PortalClientAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalClientAlert extends Model
{
    /** @use HasFactory<PortalClientAlertFactory> */
    use HasFactory;

    public const TYPE_GENERAL = 'general';

    public const TYPE_MESSAGE = 'message';

    public const TYPE_TICKET = 'ticket';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_PROFILE = 'profile';

    public const TYPE_FINANCE = 'finance';

    protected $fillable = [
        'organization_id',
        'client_id',
        'client_portal_access_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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

    public function portalAccess(): BelongsTo
    {
        return $this->belongsTo(ClientPortalAccess::class, 'client_portal_access_id');
    }
}
