<?php

declare(strict_types=1);

/**
 * The paywall-bypass proxy button appears only for hosts with a rule.
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

/** No oEmbed provider matches this host, so the page falls back to the link buttons. */
const ARTICLE_URL = 'https://folha.uol.com/materia';

const PROXIED_URL = 'https://servico-paywall.com/https://folha.uol.com/materia';

beforeEach(function () {
    Http::fake();

    seed(ReactionTypeSeeder::class);
});

function paywallLinkPost(string $url = ARTICLE_URL): Post
{
    return Post::create([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => HashId::encode(1),
        'title' => 'Uma materia com paywall',
        'slug' => 'uma-materia-com-paywall',
        'url' => $url,
        'content' => '',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('a whitelisted host gets a paywall button pointing at the proxy', function () {
    config([
        'orbita.posts.enable_paywall_bypass' => true,
        'orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com',
    ]);

    $post = paywallLinkPost();

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('Arquivo')
        ->assertSee('href="'.PROXIED_URL.'"', false);
});

it('the main link button always points at the url as posted', function () {
    config([
        'orbita.posts.enable_paywall_bypass' => true,
        'orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com',
    ]);

    $post = paywallLinkPost();

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('href="'.ARTICLE_URL.'"', false)
        ->assertSee('folha.uol.com');
});

it('a host without a rule gets no paywall button', function () {
    config([
        'orbita.posts.enable_paywall_bypass' => true,
        'orbita.posts.paywall_bypass_rules' => 'estadao.com.br = https://servico-paywall.com',
    ]);

    $post = paywallLinkPost();

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('href="'.ARTICLE_URL.'"', false)
        ->assertDontSee('Arquivo')
        ->assertDontSee('servico-paywall.com');
});

it('no paywall button when the bypass is disabled', function () {
    config([
        'orbita.posts.enable_paywall_bypass' => false,
        'orbita.posts.paywall_bypass_rules' => 'folha.uol.com = https://servico-paywall.com',
    ]);

    $post = paywallLinkPost();

    get('/p/'.$post->hashid)
        ->assertOk()
        ->assertSee('href="'.ARTICLE_URL.'"', false)
        ->assertDontSee('Arquivo');
});
