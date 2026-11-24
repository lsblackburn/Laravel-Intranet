<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// An attempt has no application row; its UUID identifies its audit event.
class AuthenticationAttempt extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public static function forAudit(): self
    {
        $attempt = new self;
        $attempt->id = (string) Str::uuid();

        return $attempt;
    }
}
