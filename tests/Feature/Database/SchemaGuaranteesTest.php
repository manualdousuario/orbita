<?php

declare(strict_types=1);

/**
 * Locks the schema constraints promised by the migrations.
 */

namespace Tests\Feature\Database;

use App\Models\Post;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\PostService;
use App\Support\HashId;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function schemaPost(User $author, array $attributes = []): Post
{
    return Post::create(array_merge([
        'user_id' => $author->id,
        'hashid' => HashId::unique('posts'),
        'title' => 'T',
        'slug' => 'p-'.uniqid(),
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ], $attributes));
}

it('the public id cannot be duplicated', function (string $table) {
    $indexes = collect(DB::select("SHOW INDEX FROM {$table}"))
        ->where('Column_name', 'hashid');

    expect($indexes->isNotEmpty() && $indexes->every(fn (object $i): bool => (int) $i->Non_unique === 0))
        ->toBeTrue("{$table}.hashid is the public lookup key and must be UNIQUE");
})->with([['posts'], ['comments']]);

/**
 * A post is persisted with its public id on the first INSERT.
 */
it('a post is never persisted without its public id', function () {
    $author = User::factory()->createOne();

    DB::enableQueryLog();
    $post = app(PostService::class)->createPost([
        'user_id' => $author->id,
        'title' => 'Com hashid desde o insert',
        'content' => 'corpo',
        'status' => 'published',
    ]);
    $inserts = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_starts_with(strtolower(trim($q['query'])), 'insert into `posts`'));
    DB::disableQueryLog();

    expect((string) $post->hashid)->not->toBe('');
    expect($inserts->count())->toBeGreaterThan(0, 'expected an insert into posts');

    foreach ($inserts as $insert) {
        expect($insert['bindings'])->not->toContain('', 'the insert must not carry an empty hashid');
    }
});

it('duplicate hashids are refused by the database', function () {
    $author = User::factory()->createOne();
    $first = schemaPost($author);

    schemaPost($author, ['hashid' => $first->hashid]);
})->throws(QueryException::class);

it('content can hold fifty thousand accented characters', function () {
    $author = User::factory()->createOne();

    // 50k chars is the validation limit; utf8mb4 doubles the bytes past TEXT capacity.
    $content = str_repeat('ç', 50000);

    $post = schemaPost($author, ['content' => $content]);

    expect(mb_strlen((string) $post->refresh()->content))->toBe(50000);
});

/**
 * Closed posts stay listed in the reader's bookmarks.
 */
it('bookmarks still list a post that was closed', function () {
    $author = User::factory()->createOne();
    $reader = User::factory()->createOne();

    $open = schemaPost($author);
    $closed = schemaPost($author, ['status' => 'closed']);

    foreach ([$open, $closed] as $post) {
        DB::table('bookmarks')->insert([
            'user_id' => $reader->id,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $listed = app(BookmarkService::class)->listFor((int) $reader->id)->pluck('id')->all();

    expect($listed)->toContain((int) $open->id);
    // A closed post must not vanish from bookmarks.
    expect($listed)->toContain((int) $closed->id);
});
