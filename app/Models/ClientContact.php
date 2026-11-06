<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    
    protected $fillable = [
        'name',
        'role',
        'email',
        'phone',
        'notes',
        'client_id'
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

}
