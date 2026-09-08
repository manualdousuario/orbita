<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;

class ContentVisibility
{
    public static function hideForReview(Post|Comment $target): bool
    {
        $status = (string) ($target->status ?? '');

        if ($status === '') {
            $target->refresh();
            $status = (string) $target->status;
        }

        $eligible = $target instanceof Post ? ['published', 'closed'] : ['visible'];

        if (! in_array($status, $eligible, true)) {
            return false;
        }

        $target->update(['status' => 'hidden']);

        return true;
    }
}
