<?php

declare(strict_types=1);

/**
 * The composer resolves the oEmbed preview while the URL is typed, without
 * calling a provider for a URL that is still half written.
 */

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

// fetchOembed() writes an oembed_cache row on every call, negative results included.
uses(RefreshDatabase::class);

const COMPOSER_VIDEO_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

beforeEach(function () {
    actingAs(User::factory()->createOne(['email_verified_at' => now()]));
    Http::preventStrayRequests();
});

function fakeYoutubeOembed(): void
{
    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response([
            'type' => 'video',
            'html' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
        ]),
    ]);
}

it('the url field looks up the preview while typing, not only on blur', function () {
    Http::fake();

    Livewire::test('⚡post-composer')
        ->assertSeeHtml('wire:model.live.debounce.700ms="url"');
});

it('a url that is still half typed does not call the provider', function () {
    Http::fake();

    Livewire::test('⚡post-composer')
        ->set('url', 'https://you')
        ->set('url', 'youtube.com/wat')
        ->set('url', 'https://')
        ->assertSet('oembedHtml', null)
        ->assertSet('oembedType', null)
        // Nothing red while the author is still mid-word.
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

it('a provider url renders the embed below the field', function () {
    fakeYoutubeOembed();

    Livewire::test('⚡post-composer')
        ->set('url', COMPOSER_VIDEO_URL)
        ->assertSet('oembedType', 'video')
        ->assertSeeHtml('oembed-frame')
        ->assertSeeHtml('<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>')
        ->assertSeeHtml('aspect-video');
});

it('the resolved preview is not refetched when the value did not really change', function () {
    fakeYoutubeOembed();

    Livewire::test('⚡post-composer')
        ->set('url', COMPOSER_VIDEO_URL)
        ->set('url', ' '.COMPOSER_VIDEO_URL.' ')
        ->assertSet('oembedType', 'video')
        ->assertSeeHtml('oembed-frame');

    Http::assertSentCount(1);
});

it('editing the url away from a provider drops the previous preview', function () {
    fakeYoutubeOembed();

    Livewire::test('⚡post-composer')
        ->set('url', COMPOSER_VIDEO_URL)
        ->assertSet('oembedType', 'video')
        ->set('url', 'https://exemplo.com.br/artigo')
        ->assertSet('oembedHtml', null)
        ->assertSet('oembedType', null)
        ->assertDontSeeHtml('oembed-frame');

    // The non-provider URL matched no regex, so only the youtube lookup went out.
    Http::assertSentCount(1);
});
