<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Post submitted by a user.
 */
class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hashid',
        'version_of',
        'user_id',
        'title',
        'url',
        'slug',
        'content',
        'is_pinned',
        'allow_comments',
        'published_at',
        'notify_replies_email',
        'notify_replies_system',
        'notify_mentions_email',
        'notify_mentions_system',
        'status',
        'score',
        'comment_count',
        'reaction_count',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'allow_comments' => 'boolean',
            'notify_replies_email' => 'boolean',
            'notify_replies_system' => 'boolean',
            'notify_mentions_email' => 'boolean',
            'notify_mentions_system' => 'boolean',
            'score' => 'integer',
            'comment_count' => 'integer',
            'reaction_count' => 'integer',
            'published_at' => 'datetime',
            'edited_at' => 'datetime',
            'last_comment_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_relationships', 'post_id', 'term_id');
    }

    public function media(): BelongsToMany
    {
        // Only post-type media; guards the shared pivot against non-post rows (e.g. old OG banners).
        return $this->belongsToMany(Media::class, 'media_relationship', 'post_id', 'media_id')
            ->where('media.media_type', Media::TYPE_POST)
            ->withPivot('display_order', 'caption')
            ->withTimestamps()
            ->orderBy('media_relationship.display_order')
            ->orderBy('media_relationship.media_id');
    }

    public function versionOf(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'version_of');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Post::class, 'version_of');
    }
}
