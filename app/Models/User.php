<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'colour'])]
#[Hidden(['password', 'remember_token', 'google2fa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! empty($user->colour)) {
                return;
            }

            $user->colour = static::generateUniqueColour();
        });
    }

    public static function generateUniqueColour(): string
    {
        do {
            $colour = sprintf('#%06X', random_int(0, 0xFFFFFF));
        } while (static::where('colour', $colour)->exists());

        return $colour;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'google2fa_secret' => 'encrypted',
            'password' => 'hashed',
            'colour' => 'string',
        ];
    }

    public function isAdmin(): bool 
    {
        return $this->role === 'admin';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->getRawOriginal('google2fa_secret'));
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
