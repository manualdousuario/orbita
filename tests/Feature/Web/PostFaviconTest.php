<?php

declare(strict_types=1);

/**
 * The external link button shows the site's favicon, or the link icon when there is none.
 */

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\User;
use App\Support\HashId;
use Database\Seeders\ReactionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReactionTypeSeeder::class);
});

function faviconLinkPost(): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => HashId::encode(1),
        'title' => 'Uma materia qualquer',
        'slug' => 'uma-materia-qualquer',
        'url' => 'https://www.folha.uol.com/materia',
        'content' => '',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('shows the favicon when duckduckgo has one', function () {
    Http::fake(['icons.duckduckgo.com/*' => Http::response('', 200), '*' => Http::response('', 404)]);

    get('/p/'.faviconLinkPost()->hashid)
        ->assertOk()
        ->assertSee('src="https://icons.duckduckgo.com/ip3/folha.uol.com.ico"', false);
});

it('falls back to the link icon when duckduckgo has none', function () {
    Http::fake(['*' => Http::response('', 404)]);

    get('/p/'.faviconLinkPost()->hashid)
        ->assertOk()
        ->assertDontSee('icons.duckduckgo.com', false);
});
