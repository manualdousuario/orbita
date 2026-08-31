<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uploaded media file used in posts, comments and avatars.
 */
class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    public const TYPE_POST = 'post';

    public const TYPE_AVATAR = 'avatar';

    public const TYPE_OGIMAGE = 'ogimage';

    protected $fillable = [
        'file_hash',
        'file_name',
        'mime_type',
        'file_extension',
        'file_type',
        'media_type',
        'path',
        'file_size',
        'metadata',
        'uploaded_by',
        'upload_ip',
        'status',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'media_relationship', 'media_id', 'post_id')
            ->withPivot('display_order', 'caption')
            ->withTimestamps();
    }

    public function comments(): BelongsToMany
    {
        return $this->belongsToMany(Comment::class, 'comment_media', 'media_id', 'comment_id')
            ->withPivot('display_order', 'caption')
            ->withTimestamps();
    }
}
