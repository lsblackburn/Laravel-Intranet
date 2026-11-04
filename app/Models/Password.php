<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Password extends Model
{
    
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

}
