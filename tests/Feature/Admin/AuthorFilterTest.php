<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\Comments\Pages\ListComments;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentService;
use App\Services\PostService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminTables;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function authorSeq(): int
{
    static $n = 0;

    return ++$n;
}

function authorUser(string $username): User
{
    return User::factory()->createOne([
        'username' => $username,
        'email_verified_at' => now(),
    ]);
}

function authorPost(User $author): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => 'af'.authorSeq(),
        'title' => 'Post de '.$author->username,
        'slug' => 'post-'.$author->username.'-'.authorSeq(),
        'content' => 'conteúdo',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function authorComment(Post $post, User $author): Comment
{
    return Comment::create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'hashid' => 'afc'.authorSeq(),
        'content' => 'comentário de '.$author->username,
        'nesting_level' => 0,
        'status' => 'visible',
    ]);
}

it('filters comments by author', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_filter'));

    $author = authorUser('autor_alvo');
    $other = authorUser('autor_outro');
    $post = authorPost($author);

    $mine = authorComment($post, $author);
    $theirs = authorComment($post, $other);

    Livewire::test(ListComments::class)
        ->filterTable('user', $author->id)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('the deep link opens the comment list already filtered', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_deeplink'));

    $author = authorUser('autor_link');
    $other = authorUser('autor_semlink');
    $post = authorPost($author);

    $mine = authorComment($post, $author);
    $theirs = authorComment($post, $other);

    Livewire::withQueryParams(['filters' => ['user' => ['value' => (string) $author->id]]])
        ->test(ListComments::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('the deep link opens the post list already filtered', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_deeplink_posts'));

    $author = authorUser('autor_post_link');
    $other = authorUser('autor_post_outro');

    $mine = authorPost($author);
    $theirs = authorPost($other);

    Livewire::withQueryParams(['filters' => ['user' => ['value' => (string) $author->id]]])
        ->test(ListPosts::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('the count columns link to the filter key Filament reads', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_url'));

    $author = authorUser('autor_url');
    authorComment(authorPost($author), $author);

    Livewire::test(ListUsers::class)
        ->assertSee('filters%5Buser%5D%5Bvalue%5D='.$author->id, false)
        ->assertDontSee('tableFilters%5Buser%5D', false);
});

it('the author counts ignore revision snapshots', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_counts'));

    $author = authorUser('autor_contagem');
    $post = authorPost($author);
    $comment = authorComment($post, $author);

    app(PostService::class)->updatePost($post, ['title' => 'Post corrigido', 'content' => 'novo conteúdo']);
    app(CommentService::class)->updateComment($comment, ['content' => 'texto corrigido']);

    expect(Post::query()->where('version_of', $post->id)->count())->toBe(1)
        ->and(Comment::query()->where('version_of', $comment->id)->count())->toBe(1);

    Livewire::test(ListUsers::class)
        ->assertTableColumnStateSet('posts_count', 1, $author)
        ->assertTableColumnStateSet('comments_count', 1, $author);
});

it('the author dropdown is not capped at a preloaded page of users', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_options'));

    $late = authorUser('zz_autor_tardio');

    $component = Livewire::test(ListComments::class);

    $select = AdminTables::filterSelect($component, 'user');

    expect($select->getOptionsFromRelationship())->toBeNull()
        ->and(AdminTables::selectFilter($component, 'user')->getSearchResultsFromRelationship($select, 'zz_autor'))
        ->toContain($late->username);
});
