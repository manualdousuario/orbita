<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reaction a user gives to a post or comment.
 */
class UserReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reaction_type_id',
        'reactable_type',
        'reactable_id',
    ];

    protected function casts(): array
    {
        return [
            'reactable_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactionType(): BelongsTo
    {
        return $this->belongsTo(ReactionType::class);
    }

    /**
     * Resolve the reacted-to model manually.
     */
    public function reactable(): ?Model
    {
        return match ($this->reactable_type) {
            'post' => Post::find($this->reactable_id),
            'comment' => Comment::find($this->reactable_id),
            default => null,
        };
    }
}
