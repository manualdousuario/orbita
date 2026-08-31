<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type of reaction a user can give to a post or comment.
 */
class ReactionType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'emoji',
        'score',
        'allowed_roles',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'allowed_roles' => 'array',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(UserReaction::class);
    }
}
