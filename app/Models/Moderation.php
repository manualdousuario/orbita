<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Moderation action taken by a moderator.
 */
class Moderation extends Model
{
    use HasFactory;

    protected $table = 'moderation';

    public $timestamps = false;

    protected $fillable = [
        'moderator_id',
        'action',
        'target_type',
        'target_id',
        'reason',
        'expires_at',
        'metadata',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
