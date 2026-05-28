<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCompanyProfile extends Model
{
    /** @use HasFactory<\Database\Factories\ClientCompanyProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'legal_name',
        'trade_name',
        'state_registration',
        'municipal_registration',
        'tax_regime',
        'main_cnae',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
