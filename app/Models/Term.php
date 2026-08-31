<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Taxonomy term (tag, category) used on posts.
 */
class Term extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Audit taxonomy changes (create/rename/retag/activate). usage_count churn is excluded.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['taxonomy', 'name', 'slug', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('term');
    }

    protected $fillable = [
        'taxonomy',
        'name',
        'slug',
        'description',
        'usage_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'term_relationships', 'term_id', 'post_id');
    }
}
