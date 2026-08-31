<?php

declare(strict_types=1);

/**
 * The feed row's per-row state is batched, so query count stays flat as posts grow.
 */

namespace Tests\Feature\Web;

use App\Models\Bookmark;
use App\Models\Media;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\User;
use App\Models\UserReaction;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function nextFeedHash(): int
{
    static $n = 1000;

    return $n++;
}

/**
 * @return list<Post>
 */
function seedFeedPosts(User $author, int $n): array
{
    $posts = [];
    for ($i = 0; $i < $n; $i++) {
        $h = nextFeedHash();
        $posts[] = Post::create([
            'user_id' => $author->id,
            'hashid' => HashId::encode($h),
            'title' => 'Post número '.$h,
            'slug' => 'post-numero-'.$h,
            'content' => 'corpo',
            'status' => 'published',
            'score' => $i,
            'published_at' => now()->subMinutes($i),
        ]);
    }

    return $posts;
}

function feedReader(): User
{
    return User::factory()->createOne([
        'username' => 'leitor_'.uniqid(),
        'email_verified_at' => now(),
    ]);
}

function countFeedQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    get(route('home'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function feedImage(User $uploader, string $name): Media
{
    return Media::create([
        'file_hash' => hash('sha256', $name.nextFeedHash()),
        'file_name' => $name.'.jpg',
        'mime_type' => 'image/jpeg',
        'file_extension' => 'jpg',
        'file_type' => 'image',
        'media_type' => Media::TYPE_POST,
        'path' => 'posts/'.$name.'.jpg',
        'file_size' => 1024,
        'uploaded_by' => $uploader->id,
    ]);
}

it('feed query count does not grow with the number of posts', function () {
    seed(ReactionTypeSeeder::class);
    $author = feedReader();

    seedFeedPosts($author, 3);
    get(route('home'))->assertOk();
    $few = countFeedQueries();

    seedFeedPosts($author, 17);
    get(route('home'))->assertOk();
    $many = countFeedQueries();

    expect($many)->toBe(
        $few,
        "The feed ran {$few} queries for 3 posts and {$many} for 20. A row is querying on its own."
    );
});

it('feed query count does not grow for an authenticated reader', function () {
    seed(ReactionTypeSeeder::class);
    $reader = feedReader();
    actingAs($reader);

    seedFeedPosts($reader, 3);
    get(route('home'))->assertOk();
    $few = countFeedQueries();

    seedFeedPosts($reader, 17);
    get(route('home'))->assertOk();
    $many = countFeedQueries();

    expect($many)->toBe($few, 'Save state or reaction state is being fetched per row.');
});

it('the feed shows the readers saved state', function () {
    seed(ReactionTypeSeeder::class);
    $reader = feedReader();
    $posts = seedFeedPosts(feedReader(), 2);

    Bookmark::create(['user_id' => $reader->id, 'post_id' => $posts[0]->id]);

    $html = (string) actingAs($reader)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('Deixar de acompanhar')
        ->toContain('Acompanhar post');
});

/** The rail shows the reaction score, not the decayed ranking score. */
it('the rail shows the reaction score not the decayed ranking score', function () {
    seed(ReactionTypeSeeder::class);
    $author = feedReader();
    $post = seedFeedPosts($author, 1)[0];
    $post->forceFill(['score' => 0])->save();

    foreach (['like' => feedReader(), 'insightful' => feedReader()] as $slug => $voter) {
        UserReaction::create([
            'user_id' => $voter->id,
            'reaction_type_id' => ReactionType::where('slug', $slug)->value('id'),
            'reactable_type' => 'post',
            'reactable_id' => $post->id,
        ]);
    }

    $html = (string) get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('aria-label="4 pontos"');
    expect(preg_replace('/\s+/', '', $html))->toContain('>+4<');
    expect(substr_count($html, 'pontos"'))->toBe(1, 'exactly one score per row');
});

it('a row shows the posts first image as a thumbnail', function () {
    seed(ReactionTypeSeeder::class);
    $author = feedReader();
    $post = seedFeedPosts($author, 1)[0];

    $post->media()->attach(feedImage($author, 'extra')->id, ['display_order' => 1]);
    $post->media()->attach(feedImage($author, 'capa')->id, ['display_order' => 0]);

    $html = (string) get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('capa.jpg')
        ->toContain('w=160');
    expect($html)->not->toContain('extra.jpg');
});

it('a row without an image has no thumbnail', function () {
    seed(ReactionTypeSeeder::class);
    seedFeedPosts(feedReader(), 1);

    $html = (string) get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('w=160');
});

it('titles carry a visited state', function () {
    seed(ReactionTypeSeeder::class);
    seedFeedPosts(feedReader(), 1);

    get(route('home'))->assertOk()->assertSee('visited:text-visited', false);
});
