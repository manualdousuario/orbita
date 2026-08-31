<?php

declare(strict_types=1);

/**
 * @mention notifications across the post/comment create/update flows.
 */

namespace Tests\Feature;

use App\Mail\MentionMail;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

function mentionUser(string $username): User
{
    return User::create([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => ucfirst($username),
        'role' => 'user',
        // Mention prefs default to OFF; opt in so the wiring is exercised.
        'notify_mentions_system' => true,
        'notify_mentions_email' => true,
    ]);
}

function mentionPost(User $author, string $content): Post
{
    return app(PostService::class)->createPost([
        'user_id' => $author->id,
        'title' => 'Post de teste',
        'content' => $content,
        'status' => 'published',
    ]);
}

it('mention in a new post notifies the mentioned user', function () {
    Mail::fake();
    $author = mentionUser('autor');
    $alice = mentionUser('alice');

    mentionPost($author, 'Olá @alice, veja isto.');

    assertDatabaseHas('notifications', ['user_id' => $alice->id, 'type' => 'mention']);
    Mail::assertQueued(MentionMail::class, fn (MentionMail $m): bool => $m->hasTo($alice->email));
});

it('author is not notified for mentioning themselves', function () {
    Mail::fake();
    $author = mentionUser('autor');

    mentionPost($author, 'Falando comigo @autor mesmo.');

    assertDatabaseMissing('notifications', ['user_id' => $author->id, 'type' => 'mention']);
    Mail::assertNotQueued(MentionMail::class);
});

it('editing a post only notifies newly added mentions', function () {
    Mail::fake();
    $author = mentionUser('autor');
    $alice = mentionUser('alice');
    $bob = mentionUser('bob');

    $post = mentionPost($author, 'Oi @alice.');

    // Alice already notified on create; clear the slate to prove the edit only adds Bob.
    Mail::fake();

    app(PostService::class)->updatePost($post, ['content' => 'Oi @alice e @bob.']);

    // Bob (new) is notified; Alice (already mentioned) is NOT re-notified.
    Mail::assertQueued(MentionMail::class, fn (MentionMail $m): bool => $m->hasTo($bob->email));
    Mail::assertNotQueued(MentionMail::class, fn (MentionMail $m): bool => $m->hasTo($alice->email));

    assertDatabaseHas('notifications', ['user_id' => $bob->id, 'type' => 'mention']);
});

it('mention in a new comment notifies the mentioned user', function () {
    Mail::fake();
    $author = mentionUser('autor');
    $commenter = mentionUser('comentarista');
    $carol = mentionUser('carol');
    $post = mentionPost($author, 'Corpo do post.');

    Mail::fake();

    app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Concordo, @carol.',
    ]);

    assertDatabaseHas('notifications', ['user_id' => $carol->id, 'type' => 'mention']);
    Mail::assertQueued(MentionMail::class, fn (MentionMail $m): bool => $m->hasTo($carol->email));
});

it('editing a comment only notifies newly added mentions', function () {
    Mail::fake();
    $author = mentionUser('autor');
    $commenter = mentionUser('comentarista');
    $carol = mentionUser('carol');
    $dave = mentionUser('dave');
    $post = mentionPost($author, 'Corpo do post.');

    $comment = app(CommentService::class)->createComment([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Oi @carol.',
    ]);

    Mail::fake();

    // updateComment enforces canEdit(), which requires the authenticated owner.
    actingAs($commenter);
    app(CommentService::class)->updateComment($comment, ['content' => 'Oi @carol e @dave.']);

    Mail::assertQueued(MentionMail::class, fn (MentionMail $m): bool => $m->hasTo($dave->email));
    Mail::assertNotQueued(MentionMail::class, fn (MentionMail $m): bool => $m->hasTo($carol->email));
});
