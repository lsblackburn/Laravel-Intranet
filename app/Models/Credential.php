<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

}
