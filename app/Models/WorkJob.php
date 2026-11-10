<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkJob extends Model
{

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    
    protected $fillable = [
        'title',
        'project_type',
        'notes',
        'client_id',
        'active',
        'completed'
    ];

}
