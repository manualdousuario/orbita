<?php

declare(strict_types=1);

/**
 * Followers of a post (people who saved it) get notified of every new comment.
 */

namespace Tests\Feature;

use App\Mail\NewCommentMail;
use App\Models\Bookmark;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

function followUser(string $username, bool $follows = true, bool $banned = false): User
{
    $user = User::create([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => ucfirst($username),
        'role' => 'user',
        'notify_follows_email' => $follows,
        'notify_follows_system' => $follows,
        // Keep the other channels quiet so assertions only see follower mail.
        'notify_replies_email' => false,
        'notify_replies_system' => false,
    ]);

    if ($banned) {
        // is_banned is guarded; only staff flips it.
        $user->is_banned = true;
        $user->save();
    }

    return $user;
}

function followPost(User $author): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => 'p'.$author->id,
        'title' => 'Post acompanhado',
        'slug' => 'post-acompanhado',
        'content' => 'conteúdo',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function comment(Post $post, User $author, string $content, ?int $parentId = null)
{
    return app(CommentService::class)->createComment(array_filter([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'content' => $content,
        'parent_id' => $parentId,
    ], fn ($value): bool => $value !== null));
}

it('notifies a follower of a top-level comment', function () {
    Mail::fake();

    $author = followUser('author');
    $follower = followUser('follower');
    $commenter = followUser('commenter');
    $post = followPost($author);
    Bookmark::create(['user_id' => $follower->id, 'post_id' => $post->id]);

    comment($post, $commenter, 'Novidade');

    Mail::assertQueued(
        NewCommentMail::class,
        fn (NewCommentMail $mail): bool => $mail->hasTo($follower->email) && $mail->following
    );

    assertDatabaseHas('notifications', [
        'user_id' => $follower->id,
        'type' => 'comment',
        'title' => 'Novo comentário em post que você acompanha',
    ]);
});

it('notifies a follower of a reply too', function () {
    $author = followUser('author');
    $follower = followUser('follower');
    $commenter = followUser('commenter');
    $replier = followUser('replier');
    $post = followPost($author);
    $parent = comment($post, $commenter, 'Primeiro');

    Bookmark::create(['user_id' => $follower->id, 'post_id' => $post->id]);
    Mail::fake();

    comment($post, $replier, 'Respondendo', $parent->id);

    Mail::assertQueued(
        NewCommentMail::class,
        fn (NewCommentMail $mail): bool => $mail->hasTo($follower->email)
    );
});

it('sends nothing to a follower who turned the preference off', function () {
    Mail::fake();

    $author = followUser('author');
    $follower = followUser('follower', follows: false);
    $commenter = followUser('commenter');
    $post = followPost($author);
    Bookmark::create(['user_id' => $follower->id, 'post_id' => $post->id]);

    comment($post, $commenter, 'Novidade');

    Mail::assertNotQueued(
        NewCommentMail::class,
        fn (NewCommentMail $mail): bool => $mail->hasTo($follower->email)
    );
    assertDatabaseMissing('notifications', ['user_id' => $follower->id]);
});

it('sends nothing to a banned follower', function () {
    Mail::fake();

    $author = followUser('author');
    $banned = followUser('banned', banned: true);
    $commenter = followUser('commenter');
    $post = followPost($author);
    Bookmark::create(['user_id' => $banned->id, 'post_id' => $post->id]);

    comment($post, $commenter, 'Novidade');

    Mail::assertNotQueued(
        NewCommentMail::class,
        fn (NewCommentMail $mail): bool => $mail->hasTo($banned->email)
    );
    assertDatabaseMissing('notifications', ['user_id' => $banned->id]);
});

it('never notifies the commenter about their own comment', function () {
    Mail::fake();

    $author = followUser('author');
    $commenter = followUser('commenter');
    $post = followPost($author);
    Bookmark::create(['user_id' => $commenter->id, 'post_id' => $post->id]);

    comment($post, $commenter, 'Comentando no post que acompanho');

    Mail::assertNothingQueued();
    assertDatabaseMissing('notifications', ['user_id' => $commenter->id]);
});

it('does not double up on the post author who follows their own post', function () {
    Mail::fake();

    // The author opts into reply mail, so the author-facing notification is the one that fires.
    $author = followUser('author');
    $author->update(['notify_replies_email' => true, 'notify_replies_system' => true]);
    $commenter = followUser('commenter');
    $post = followPost($author);
    Bookmark::create(['user_id' => $author->id, 'post_id' => $post->id]);

    comment($post, $commenter, 'Novidade');

    Mail::assertQueuedCount(1);
    expect(Notification::query()->where('user_id', $author->id)->count())->toBe(1);
});

it('does not double up on the parent comment author who follows the post', function () {
    $author = followUser('author');
    $parentAuthor = followUser('parentauthor');
    $parentAuthor->update(['notify_replies_email' => true, 'notify_replies_system' => true]);
    $replier = followUser('replier');
    $post = followPost($author);
    $parent = comment($post, $parentAuthor, 'Primeiro');

    Bookmark::create(['user_id' => $parentAuthor->id, 'post_id' => $post->id]);
    Mail::fake();

    comment($post, $replier, 'Respondendo', $parent->id);

    Mail::assertQueuedCount(1);
    expect(Notification::query()->where('user_id', $parentAuthor->id)->count())->toBe(1);
});
