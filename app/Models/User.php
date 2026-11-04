<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A customer or admin. Wallet balance is stored here in USD cents (see WalletService).
 */
#[Fillable(['name', 'email', 'password', 'google_id', 'facebook_id', 'avatar', 'phone'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'admin_code_hash', 'email_otp'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance_minor' => 'int',
            'is_admin' => 'bool',
            'suspended_at' => 'datetime',
            'kyc_decided_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'admin_code_expires_at' => 'datetime',
        ];
    }

    /**
     * Generate a fresh 6-digit email verification code, valid for 10 minutes.
     */
    public function issueEmailOtp(): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'email_otp' => $this->hashOtp($otp), // stored hashed: a database leak must not reveal live codes
            'email_otp_expires_at' => now()->addMinutes(10),
            'email_otp_attempts' => 0,
        ])->save();

        return $otp;
    }

    /**
     * Checks a submitted verification code: unexpired, at most 5 wrong guesses per issued code, constant-time
     * compare. A successful check does not clear it; the caller marks the email verified and calls clearEmailOtp().
     */
    public function checkEmailOtp(string $otp): bool
    {
        if (! $this->email_otp || ! $this->email_otp_expires_at || now()->isAfter($this->email_otp_expires_at) || (int) $this->email_otp_attempts >= 5) {
            return false;
        }
        if (hash_equals((string) $this->email_otp, $this->hashOtp($otp))) {
            return true;
        }
        $this->forceFill(['email_otp_attempts' => (int) $this->email_otp_attempts + 1])->saveQuietly();

        return false;
    }

    public function clearEmailOtp(): void
    {
        $this->forceFill(['email_otp' => null, 'email_otp_expires_at' => null, 'email_otp_attempts' => 0]);
    }

    private function hashOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key'));
    }

    /**
     * Admins are either flagged in the database or allow-listed by email
     * (config/admin.php, ADMIN_EMAILS).
     */
    public function isAdmin(): bool
    {
        if ((bool) $this->is_admin) {
            return true;
        }

        $email = mb_strtolower(trim((string) $this->email));

        return $email !== '' && in_array($email, config('admin.emails', []), true);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function virtualNumberSubscriptions(): HasMany
    {
        return $this->hasMany(VirtualNumberSubscription::class);
    }

    public function socialNumberOrders(): HasMany
    {
        return $this->hasMany(SocialNumberOrder::class);
    }

    public function socialNumberRentals(): HasMany
    {
        return $this->hasMany(SocialNumberRental::class);
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }
}
