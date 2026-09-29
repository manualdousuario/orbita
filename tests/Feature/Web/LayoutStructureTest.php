<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Post;
use App\Models\Term;
use App\Models\User;
use App\Support\HashId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function layoutPost(User $author, int $n, array $overrides = []): Post
{
    return Post::create(array_merge([
        'user_id' => $author->id,
        'hashid' => HashId::encode(9000 + $n),
        'title' => 'Post de layout '.$n,
        'slug' => 'post-de-layout-'.$n,
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now()->subHour(),
    ], $overrides));
}

it('the profile shows one tab at a time with counts', function () {
    $user = User::factory()->createOne(['username' => 'abas', 'email_verified_at' => now()]);
    $post = layoutPost($user, 1);

    DB::table('comments')->insert([
        'hashid' => HashId::encode(9101), 'user_id' => $user->id, 'post_id' => $post->id,
        'content' => 'Um **comentário** qualquer.', 'status' => 'visible',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    get('/u/abas')->assertOk()
        ->assertSee('Post de layout 1')
        ->assertDontSee('Um comentário qualquer.')
        ->assertSee('href="'.route('users.profile.comments', ['username' => 'abas']).'"', false);

    get('/u/abas/comentarios')->assertOk()
        ->assertSee('Um comentário qualquer.')
        ->assertDontSee('**comentário**');
});

it('an empty popular week shows the latest posts instead of a dead end', function () {
    $author = User::factory()->createOne(['username' => 'antigo', 'email_verified_at' => now()]);
    layoutPost($author, 2, ['published_at' => now()->subMonth(), 'title' => 'Post do mês passado']);

    get(route('home'))->assertOk()
        ->assertSee('Nada ganhou tração nos últimos 7 dias.')
        ->assertSee('Post do mês passado')
        ->assertDontSee('Seja o primeiro a postar!');
});

it('feed rows link the post tags', function () {
    $author = User::factory()->createOne(['username' => 'tagueador', 'email_verified_at' => now()]);
    $post = layoutPost($author, 3);
    $tag = Term::create(['taxonomy' => 'tag', 'slug' => 'astronomia', 'name' => 'Astronomia', 'is_active' => true]);
    $post->terms()->attach($tag->id);

    get(route('home.all'))->assertOk()
        ->assertSee('href="'.route('tags.show', ['slug' => 'astronomia']).'"', false)
        ->assertSee('#Astronomia');
});

it('a closed thread explains itself under the comments heading', function () {
    $author = User::factory()->createOne(['username' => 'fechador', 'email_verified_at' => now()]);
    $post = layoutPost($author, 4, ['status' => 'closed', 'allow_comments' => false]);

    $html = (string) get(route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]))
        ->assertOk()->getContent();

    expect(strpos($html, 'Comentários ('))->toBeLessThan(strpos($html, 'Os comentários fecharam depois de'));
    expect($html)->toContain('Ninguém comentou neste post.');
});
