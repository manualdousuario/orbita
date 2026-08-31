<?php

declare(strict_types=1);

/**
 * Regressions found by the second workflow-backed review round.
 */

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Pipeline\Image\ImageUploadContext;
use App\Pipeline\Image\StoreImageStage;
use App\Services\CommentService;
use App\Services\PostService;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Throwable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

function r2User(string $tag = 'u'): User
{
    return User::factory()->createOne(['username' => 'r2_'.$tag]);
}

/** `created_at` is not fillable, so it must be backdated with a raw update. */
function r2Backdate(string $table, int $id, DateTimeInterface $when): void
{
    DB::table($table)->where('id', $id)->update(['created_at' => $when]);
}

/**
 * @param  array<string, mixed>  $attrs
 */
function r2Post(User $u, string $tag, array $attrs = []): Post
{
    return Post::create(array_merge([
        'user_id' => $u->id,
        'hashid' => 'r2'.$tag,
        'title' => 'Titulo '.$tag,
        'slug' => 'titulo-'.$tag,
        'content' => 'corpo '.$tag,
        'status' => 'published',
        'published_at' => now(),
        'allow_comments' => true,
    ], $attrs));
}

// 1) A failed disk write must not leave an orphaned Media row with a poisoned file_hash.

it('failed disk write leaves no orphaned media row', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->andThrow(new RuntimeException('disk full'));
    // The stage must also try to clean up any bytes that did land.
    $disk->shouldReceive('exists')->andReturn(false);

    $user = r2User('img');

    $ctx = new ImageUploadContext(['name' => 'a.jpg'], 'posts', (int) $user->id);
    $ctx->fileHash = str_repeat('a', 64);
    $ctx->mimeType = 'image/jpeg';
    $ctx->extension = 'jpg';
    $ctx->processedImageBytes = 'not-really-jpeg-bytes';

    $before = Media::query()->count();

    $failure = null;

    try {
        (new StoreImageStage($disk))($ctx);
    } catch (Throwable $e) {
        $failure = $e;
    }

    expect($failure)->not->toBeNull('the stage must propagate the disk failure');
    expect($failure?->getMessage())->toContain('disk full');

    expect(Media::query()->count())->toBe($before, 'the media row must be rolled back with the failed write');
    assertDatabaseMissing('media', ['file_hash' => str_repeat('a', 64)]);
});

it('editing a post does not trip duplicate detection', function () {
    config(['orbita.antispam.post_cooldown' => 0, 'orbita.antispam.duplicate_detection' => true]);

    $user = r2User('dup');
    $posts = app(PostService::class);

    $old = r2Post($user, 'dup');
    r2Backdate('posts', (int) $old->id, now()->subHours(2));

    // The revision snapshot is stamped now(), so it is what the duplicate query can see.
    $posts->updatePost($old->fresh(), ['title' => 'Reciclado', 'content' => 'corpo reciclado']);

    assertDatabaseHas('posts', ['version_of' => $old->id, 'title' => 'Titulo dup']);

    $result = $posts->checkAntispam((int) $user->id, 'Titulo dup', null, 'corpo dup');

    expect($result['allowed'])->toBeTrue(
        'the duplicate matched against its own revision: '.($result['reason'] ?? ''),
    );
});

it('editing a comment does not trip the comment antispam cooldown', function () {
    config(['orbita.antispam.comment_cooldown' => 30, 'orbita.comments.edit_time_limit' => 0]);

    $user = r2User('ac');
    $post = r2Post($user, 'ac');
    $comments = app(CommentService::class);

    $comment = Comment::create([
        'user_id' => $user->id, 'post_id' => $post->id, 'hashid' => 'r2c1',
        'content' => 'original', 'status' => 'visible', 'nesting_level' => 0,
    ]);
    r2Backdate('comments', (int) $comment->id, now()->subHour());

    actingAs($user);
    $comments->updateComment($comment->fresh(), ['content' => 'editado']);

    $result = $comments->checkAntispam((int) $user->id, (int) $post->id, 'um comentario novo');

    expect($result['allowed'])->toBeTrue('a revision is not a new comment: '.($result['reason'] ?? ''));
});

// 6) Re-saving unchanged legacy content must not trip a post-dated content rule.

it('unchanged legacy content can still be saved', function () {
    config(['orbita.comments.edit_time_limit' => 0]);

    $user = r2User('leg');
    $post = r2Post($user, 'leg');

    // A row that predates the content gate, e.g. imported before conversion existed.
    $comment = Comment::create([
        'user_id' => $user->id, 'post_id' => $post->id, 'hashid' => 'r2leg',
        'content' => '<p>html antigo</p>', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    actingAs($user);

    $updated = app(CommentService::class)->updateComment($comment, [
        'content' => '<p>html antigo</p>',
        'status' => 'hidden',
    ]);

    expect($updated->status)->toBe('hidden');
});

it('newly submitted html is still rejected', function () {
    config(['orbita.comments.edit_time_limit' => 0]);

    $user = r2User('rej');
    $post = r2Post($user, 'rej');

    $comment = Comment::create([
        'user_id' => $user->id, 'post_id' => $post->id, 'hashid' => 'r2rej',
        'content' => 'limpo', 'status' => 'visible', 'nesting_level' => 0,
    ]);

    actingAs($user);

    app(CommentService::class)->updateComment($comment, ['content' => '<script>alert(1)</script>']);
})->throws(RuntimeException::class);

// 5) An admin edit is still an edit: Filament must go through the service.

it('filament post edit writes a revision and stamps edited at', function () {
    $author = r2User('fa');
    $admin = User::factory()->createOne(['username' => 'r2_admin', 'role' => 'admin']);

    $post = r2Post($author, 'fil');

    Livewire::actingAs($admin)
        ->test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Titulo corrigido pelo admin', 'content' => 'corpo corrigido'])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();

    expect($post->title)->toBe('Titulo corrigido pelo admin')
        ->and($post->edited_at)->not->toBeNull('an admin edit must stamp edited_at')
        ->and($post->slug)->toBe('titulo-corrigido-pelo-admin', 'the slug must follow the title');

    assertDatabaseHas('posts', ['version_of' => $post->id, 'title' => 'Titulo fil']);
});

// 7) Root pagination must be stable: every comment appears on exactly one page.

it('pagination is stable when the sort column is all ties', function () {
    config(['orbita.comments.per_page' => 10]);

    $user = r2User('tie');
    $post = r2Post($user, 'tie');

    ReactionType::firstOrCreate(
        ['slug' => 'like'],
        ['name' => 'Curtir', 'emoji' => '👍', 'score' => 1, 'allowed_roles' => ['user'], 'display_order' => 1],
    );

    // Every root ties on both sort columns (created_at is forced afterwards).
    $stamp = now()->subDay();
    for ($i = 0; $i < 25; $i++) {
        $c = Comment::create([
            'user_id' => $user->id, 'post_id' => $post->id, 'hashid' => 'tie'.$i,
            'content' => 'c'.$i, 'status' => 'visible', 'nesting_level' => 0,
            'reaction_count' => 0,
        ]);
        r2Backdate('comments', (int) $c->id, $stamp);
    }

    foreach (['most_reactions', 'newest', 'oldest'] as $sort) {
        $component = Livewire::withQueryParams(['sort' => $sort])
            ->test('comment-tree', ['postId' => $post->id, 'allowComments' => true]);

        // The pages must partition the roots: with every sort key tied, only the id
        // tie-break keeps a comment from landing on two pages or none.
        $pages = [];
        $pages[] = array_column($component->instance()->tree, 'id');
        expect($pages[0])->toHaveCount(10, "sort={$sort}");

        $component->call('loadMore', 'comentarios');
        $pages[] = array_column($component->instance()->tree, 'id');
        expect($pages[1])->toHaveCount(10, "sort={$sort}");

        $component->call('loadMore', 'comentarios');
        $pages[] = array_column($component->instance()->tree, 'id');
        expect($pages[2])->toHaveCount(5, "sort={$sort}");

        $all = array_merge(...$pages);
        expect(array_unique($all))->toHaveCount(
            25,
            "sort={$sort}: pagination lost or duplicated comments (ORDER BY with no tie-breaker)",
        );
    }
});
