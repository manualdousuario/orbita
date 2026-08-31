<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when users are mentioned in a post or comment.
 */
class UsersMentioned
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<User>  $users  the already-filtered recipients to notify
     * @param  string  $message  notification body text
     * @param  string  $link  deep link to the post/comment
     */
    public function __construct(
        public readonly array $users,
        public readonly string $message,
        public readonly string $link,
    ) {}
}
