<?php

declare(strict_types=1);

/**
 * Revision snapshot rows stay hidden from the admin listings.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\Comments\Pages\ListComments;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function revisionSeq(): int
{
    static $n = 0;

    return ++$n;
}

function revisionStaff(string $role = 'admin'): User
{
    return AdminUsers::staff($role, $role.'_'.uniqid());
}

function revisionPost(?User $author = null): Post
{
    return Post::create([
        'user_id' => ($author ?? User::factory()->createOne(['email_verified_at' => now()]))->id,
        'hashid' => 'rv'.revisionSeq(),
        'title' => 'Post original',
        'slug' => 'post-original-'.uniqid(),
        'content' => 'conteúdo original',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function revisionComment(Post $post, ?User $author = null): Comment
{
    return Comment::create([
        'post_id' => $post->id,
        'user_id' => ($author ?? User::factory()->createOne(['email_verified_at' => now()]))->id,
        'hashid' => 'rvc'.revisionSeq(),
        'content' => 'texto original ofensivo',
        'nesting_level' => 0,
        'status' => 'visible',
    ]);
}

it('the comments list shows the corrected version and not the snapshot', function () {
    Filament::setCurrentPanel('admin');
    actingAs(revisionStaff());

    $post = revisionPost();
    $comment = revisionComment($post);

    app(CommentService::class)->updateComment($comment, ['content' => 'texto corrigido']);

    $revision = Comment::query()->where('version_of', $comment->id)->firstOrFail();
    expect((string) $revision->content)->toBe('texto original ofensivo')
        ->and((string) $revision->status)->toBe('revision');

    Livewire::test(ListComments::class)
        ->assertCanSeeTableRecords([$comment->refresh()])
        ->assertCanNotSeeTableRecords([$revision]);
});

it('the posts list shows the corrected version and not the snapshot', function () {
    Filament::setCurrentPanel('admin');
    actingAs(revisionStaff());

    $post = revisionPost();

    app(PostService::class)->updatePost($post, ['title' => 'Post corrigido', 'content' => 'conteúdo corrigido']);

    $revision = Post::query()->where('version_of', $post->id)->firstOrFail();
    expect((string) $revision->content)->toBe('conteúdo original');

    Livewire::test(ListPosts::class)
        ->assertCanSeeTableRecords([$post->refresh()])
        ->assertCanNotSeeTableRecords([$revision]);
});

it('the revisions action appears only once a snapshot exists', function () {
    Filament::setCurrentPanel('admin');
    actingAs(revisionStaff());

    $post = revisionPost();
    $untouched = revisionComment($post);
    $edited = revisionComment($post);

    app(CommentService::class)->updateComment($edited, ['content' => 'texto corrigido']);

    Livewire::test(ListComments::class)
        ->assertTableActionHidden('revisions', $untouched)
        ->assertTableActionVisible('revisions', $edited->refresh());

    expect(route('comments.revisions', ['hashid' => $edited->hashid]))
        ->toEndWith('/c/'.$edited->hashid.'/revisions');
});

it('the history page stays open to the author and staff only', function () {
    $author = User::factory()->createOne(['email_verified_at' => now()]);
    $post = revisionPost();
    $comment = revisionComment($post, $author);

    actingAs($author);
    app(CommentService::class)->updateComment($comment, ['content' => 'texto corrigido']);

    $url = '/c/'.$comment->hashid.'/revisions';

    actingAs($author)->get($url)->assertOk();
    actingAs(revisionStaff('moderator'))->get($url)->assertOk();
    actingAs(User::factory()->createOne(['email_verified_at' => now()]))->get($url)->assertForbidden();
});
