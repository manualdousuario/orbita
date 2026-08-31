<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User-submitted report on a post or comment; the target is resolved lazily and memoised.
 */
class Report extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'read_at',
        'read_by',
    ];

    /**
     * Resolved post/comment, memoised so a table row never re-queries.
     */
    private Post|Comment|null|false $targetCache = false;

    protected function casts(): array
    {
        return [
            'reportable_id' => 'integer',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function readBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function target(): Post|Comment|null
    {
        if ($this->targetCache === false) {
            $this->targetCache = $this->reportable_type === 'post'
                ? Post::find($this->reportable_id)
                : Comment::find($this->reportable_id);
        }

        return $this->targetCache;
    }
}
