<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-use token for account actions (email change, social link, deletion).
 */
class UserToken extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token',
        'token_type',
        'email',
        'payload',
        'is_used',
        'used_at',
        'request_ip',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return Builder<static>
     */
    public static function wherePlainToken(string $plain): Builder
    {
        return static::query()->where('token', self::hashToken($plain));
    }

    public function consume(): bool
    {
        $now = now();

        $claimed = static::query()
            ->whereKey($this->getKey())
            ->where('is_used', false)
            ->update(['is_used' => true, 'used_at' => $now]);

        if ($claimed !== 1) {
            return false;
        }

        $this->forceFill(['is_used' => true, 'used_at' => $now])->syncOriginal();

        return true;
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
