<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    
    protected $fillable = [
        'name',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

}
