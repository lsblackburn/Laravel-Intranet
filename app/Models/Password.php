<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Password extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    protected $fillable = [
        'name',
        'username',
        'password',
        'notes',
        'client_id',
    ];

}
