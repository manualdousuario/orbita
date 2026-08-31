<?php

declare(strict_types=1);

/**
 * The seven home feed tabs.
 */

namespace Tests\Feature\Web;

use App\Services\RankingService;
use App\Support\HomeFeedTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Mockery;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('every tab route renders with its label', function () {
    foreach (HomeFeedTabs::all() as $meta) {
        get($meta['path'])
            ->assertOk()
            ->assertSee($meta['label'])
            ->assertSee($meta['description']);
    }
});

it('root defaults to popular', function () {
    get('/')->assertOk()->assertSee('Populares');
});

/** Each tab must call its own RankingService method. */
it('each tab calls its own ranking method', function () {
    foreach (HomeFeedTabs::all() as $key => $meta) {
        $mock = Mockery::mock(RankingService::class);

        if (($meta['kind'] ?? 'posts') === 'comments') {
            $mock->shouldReceive('paginateComments')
                ->once()
                ->with(Mockery::any(), Mockery::any())
                ->andReturn(new LengthAwarePaginator([], 0, 25, 1));
        } else {
            $mock->shouldReceive('paginatePosts')
                ->once()
                ->with($meta['method'], Mockery::any(), Mockery::any())
                ->andReturn(new LengthAwarePaginator([], 0, 25, 1));
        }

        app()->instance(RankingService::class, $mock);

        Livewire::test('home-feed', ['tab' => $key])->assertOk();

        Mockery::close();
    }
});

it('unknown tab falls back to the default', function () {
    Livewire::test('home-feed', ['tab' => 'nao-existe'])
        ->assertSet('tab', HomeFeedTabs::DEFAULT);
});

it('all ranking methods exist on the service', function () {
    foreach (HomeFeedTabs::all() as $meta) {
        expect(method_exists(RankingService::class, $meta['method']))->toBeTrue(
            "RankingService::{$meta['method']}() is referenced by a tab but does not exist",
        );
    }
});
