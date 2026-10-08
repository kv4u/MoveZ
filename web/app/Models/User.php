<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * api_token is deliberately excluded — set it via issueApiToken().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

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
        ];
    }

    /**
     * Generate a new API token, store its SHA-256 hash and return the plaintext.
     * The plaintext is shown once and never stored.
     */
    public function issueApiToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $token;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function syncBlob(): HasOne
    {
        return $this->hasOne(SyncBlob::class);
    }
}
