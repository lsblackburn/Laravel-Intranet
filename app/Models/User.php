<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\LeaveSetting;
use Carbon\Carbon;

#[Fillable(['name', 'email', 'password', 'colour', 'employment_start_date'])]
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

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function calculateLeaveAllowance(): float
    {
        $settings = LeaveSetting::first();

        if (! $settings || ! $settings->base_allowance) {
            // If no settings are found, or base allowance is not set, return the user's leave_allowance or default to 20
            return (float) $this->leave_allowance;
        }

        $yearsWorked = Carbon::parse($this->employment_start_date)->diffInYears(now());

        if ($yearsWorked < $settings->increase_after_years) { 
            // If user hasn't reached the threshold for increase, return base allowance
            return (float) $settings->base_allowance;
        }

        $extraYears = $yearsWorked - $settings->increase_after_years + 1;
        // Calculate the allowance based on the base allowance and the increase for extra years, ensuring it does not exceed the maximum allowance

        $allowance = $settings->base_allowance + ($extraYears * $settings->increase_by_days);

        return min($allowance, $settings->maximum_allowance);
    }

    public function approvedLeaveDaysUsed(): float
    {
        return $this->leaves()
            ->where('status', 'approved')
            ->get()
            ->sum(function($leave){
                if ($leave->is_half_day) {
                    return 0.5;
                }

                return Carbon::parse($leave->start_date)
                ->diffInDays(Carbon::parse($leave->end_date)) + 1;
            });
    }

    public function remainingLeaveAllowance(): float
    {
        return $this->leave_allowance - $this->approvedLeaveDaysUsed();
    }

}
