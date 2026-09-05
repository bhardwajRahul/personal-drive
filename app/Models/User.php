<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'username',
        'is_admin',
        'password',
        'google2fa_secret',
        'google2fa_enabled'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function getTwoFactorStatus(): bool
    {
        return $this->google2fa_enabled ?? false;
    }

    public function getTwoFactorSecret(): string
    {
        return $this->google2fa_secret ?? '';
    }

    public function setTwoFactorSecret(string $secret): bool
    {
        return $this->update(['google2fa_secret' => $secret]);
    }

    public function setTwoFactorStatus(bool $status): bool
    {
        return $this->update(['google2fa_enabled' => $status]);
    }

    public function getApiTokens()
    {
        return $this->tokens()
            ->select('id', 'name', 'created_at', 'last_used_at', 'abilities')
            ->get();
    }

    public function deleteToken(string $tokenId): bool
    {
        return (bool) $this->tokens()->where('id', $tokenId)->delete();
    }
}
