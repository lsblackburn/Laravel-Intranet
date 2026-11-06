<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

}