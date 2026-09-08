<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comment on a post, supporting replies and revisions.
 */
class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hashid',
        'version_of',
        'user_id',
        'post_id',
        'parent_id',
        'content',
        'nesting_level',
        'status',
        'score',
        'reaction_count',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'nesting_level' => 'integer',
            'score' => 'integer',
            'reaction_count' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function versionOf(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'version_of');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Comment::class, 'version_of');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'comment_media', 'comment_id', 'media_id')
            ->withPivot('display_order', 'caption')
            ->withTimestamps()
            ->orderBy('comment_media.display_order');
    }
}
