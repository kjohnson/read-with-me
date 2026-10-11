<?php

namespace App\Models;

use Database\Factories\MagicLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['token_hash', 'expires_at'])]
class MagicLink extends Model
{
    /** @use HasFactory<MagicLinkFactory> */
    use HasFactory;

    /**
     * The number of minutes a magic link remains valid.
     */
    public const int LIFETIME_MINUTES = 15;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Issue a new magic link for the given user, returning the plain-text token.
     */
    public static function issueFor(User $user): string
    {
        $token = Str::random(64);

        $user->magicLinks()->create([
            'token_hash' => self::hashToken($token),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $token;
    }

    /**
     * Find a usable (unexpired and unused) magic link by its plain-text token.
     */
    public static function findUsable(string $token): ?MagicLink
    {
        return self::query()
            ->where('token_hash', self::hashToken($token))
            ->usable()
            ->first();
    }

    /**
     * Hash a plain-text token for storage and lookup.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Scope the query to links that are unexpired and unused.
     *
     * @param  Builder<MagicLink>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    /**
     * Get the user the link belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the link as used so it cannot be used again.
     */
    public function markAsUsed(): bool
    {
        return self::query()
            ->whereKey($this->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }
}
