<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class BannedContent
{
    public static function hide(User $user): void
    {
        $posts = Post::query()
            ->where('user_id', $user->id)
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->get()
            ->filter(fn (Post $post): bool => ContentVisibility::hideForReview($post))
            ->map(fn (Post $post): int => (int) $post->id)
            ->values()
            ->all();

        $comments = Comment::query()
            ->where('user_id', $user->id)
            ->whereNull('version_of')
            ->where('status', 'visible')
            ->get()
            ->filter(fn (Comment $comment): bool => ContentVisibility::hideForReview($comment))
            ->map(fn (Comment $comment): int => (int) $comment->id)
            ->values()
            ->all();

        User::query()->whereKey($user->id)->update(['remember_token' => Str::random(60)]);

        if ($posts === [] && $comments === []) {
            return;
        }

        ModerationLogger::system(
            'hide',
            'user',
            (int) $user->id,
            'Conteúdo ocultado pelo banimento',
            ['posts' => $posts, 'comments' => $comments],
        );
    }
}
