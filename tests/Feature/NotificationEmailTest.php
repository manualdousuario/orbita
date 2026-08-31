<?php

declare(strict_types=1);

/**
 * Comment-notification email channel, gated by the author's preferences.
 */

namespace Tests\Feature;

use App\Mail\NewCommentMail;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function notifyUser(string $username, bool $notifyRepliesEmail, bool $notifyRepliesSystem = true): User
{
    return User::create([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => ucfirst($username),
        'role' => 'user',
        'notify_replies_email' => $notifyRepliesEmail,
        // The column defaults to false; set the system pref explicitly when a row is wanted.
        'notify_replies_system' => $notifyRepliesSystem,
    ]);
}

function notifyPost(User $author): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => 'p'.$author->id,
        'title' => 'Meu post',
        'slug' => 'meu-post',
        'content' => 'conteúdo',
        'status' => 'published',
        'published_at' => now(),
        // notify_replies_* left null => fall through to the user preference.
    ]);
}

it('new comment queues mail when author opted in', function () {
    Mail::fake();

    $author = notifyUser('author', notifyRepliesEmail: true);
    $commenter = notifyUser('commenter', notifyRepliesEmail: true);
    $post = notifyPost($author);

    app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Ótimo post!',
    ]);

    // Email channel: the queued Mailable is addressed to the post author.
    Mail::assertQueued(
        NewCommentMail::class,
        fn (NewCommentMail $mail): bool => $mail->hasTo($author->email)
    );

    // System channel: the notification row is created regardless.
    assertDatabaseHas('notifications', [
        'user_id' => $author->id,
        'type' => 'comment',
    ]);
});

it('new comment does not queue mail when author opted out', function () {
    Mail::fake();

    $author = notifyUser('author', notifyRepliesEmail: false);
    $commenter = notifyUser('commenter', notifyRepliesEmail: true);
    $post = notifyPost($author);

    app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Outro comentário',
    ]);

    // Email channel is suppressed by the notify_replies_email = false pref.
    Mail::assertNotQueued(NewCommentMail::class);

    // System channel is independent and still writes the notification row.
    assertDatabaseHas('notifications', [
        'user_id' => $author->id,
        'type' => 'comment',
    ]);
});

/**
 * Each channel (email, system row) fires exactly once per comment.
 */
it('new comment notifies exactly once per channel', function () {
    Mail::fake();

    $author = notifyUser('author', notifyRepliesEmail: true);
    $commenter = notifyUser('commenter', notifyRepliesEmail: true);
    $post = notifyPost($author);

    app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Ótimo post!',
    ]);

    Mail::assertQueuedCount(1);

    expect(
        Notification::query()
            ->where('user_id', $author->id)
            ->where('type', 'comment')
            ->count()
    )->toBe(1);
});
