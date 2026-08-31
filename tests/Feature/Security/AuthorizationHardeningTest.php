<?php

declare(strict_types=1);

/**
 * Regression net for authorization: mass assignment, redirects, and Filament resources.
 */

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Moderations\ModerationResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use App\Support\Url;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function authzUser(array $attributes = []): User
{
    $user = new User;

    $user->forceFill(array_merge([
        'username' => 'u'.fake()->unique()->numberBetween(1000, 999999),
        'email' => fake()->unique()->safeEmail(),
        'password' => 'secret123',
        'display_name' => 'Fulano',
        'role' => UserRole::User->value,
        'is_banned' => false,
        'email_verified_at' => now(),
    ], $attributes))->save();

    return $user;
}

// ---------------------------------------------------------------- mass assignment

/**
 * Role, is_banned and email_verified_at must not be mass-assignable.
 */
it('role is banned and email verified at are not mass assignable', function () {
    $user = authzUser(['email_verified_at' => null]);

    $user->update([
        'display_name' => 'Nome novo',
        'role' => UserRole::Admin->value,
        'is_banned' => true,
        'email_verified_at' => now(),
    ]);

    $user->refresh();

    expect($user->display_name)->toBe('Nome novo', 'the legitimate field must still be written')
        ->and($user->role)->toBe(UserRole::User)
        ->and((bool) $user->is_banned)->toBeFalse()
        ->and($user->email_verified_at)->toBeNull();
});

it('force fill remains the way to change a privilege', function () {
    $user = authzUser();

    $user->forceFill(['role' => UserRole::Admin->value])->save();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});

// ---------------------------------------------------------------- open redirect

it('only local targets are accepted as a redirect', function (string $target, bool $expected) {
    expect(Url::isLocal($target))->toBe($expected, $target);
})->with([
    'relative path' => ['/bookmarks', true],
    'root' => ['/', true],
    'protocol relative' => ['//evil.example', false],
    'backslash disguised' => ['/\\evil.example', false],
    'absolute foreign host' => ['https://evil.example/pwned', false],
    'javascript scheme' => ['javascript:alert(1)', false],
    'empty' => ['', false],
]);

/**
 * isLocal() trusts config('orbita.url'), never the request Host.
 */
it('the configured host is the authority not the request host', function () {
    config(['orbita.url' => 'https://orbita.example.com']);

    expect(Url::isLocal('https://orbita.example.com/p/abc'))->toBeTrue()
        ->and(Url::isLocal('https://localhost/p/abc'))->toBeFalse();
});

it('social login does not stash a foreign redirect target', function () {
    get('/auth/google?redirect_to=https://evil.example/pwned');

    expect(session('url.intended'))->toBeNull();
});

// ---------------------------------------------------------------- service authorization

it('update post refuses an authenticated stranger', function () {
    $author = authzUser();
    $stranger = authzUser();

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'authz1',
        'title' => 'T',
        'slug' => 't-authz1',
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);

    actingAs($stranger);

    app(PostService::class)->updatePost($post, ['title' => 'Sequestrado']);
})->throws(AuthorizationException::class);

it('update post still works for a system caller with no actor', function () {
    $author = authzUser();

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'authz2',
        'title' => 'T',
        'slug' => 't-authz2',
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(PostService::class)->updatePost($post, ['title' => 'Corrigido pelo console']);

    expect($post->refresh()->title)->toBe('Corrigido pelo console');
});

// ---------------------------------------------------------------- Filament resources

/**
 * Filament moderation resources are staff-only.
 */
it('moderation resources are staff only and say so', function (string $resource) {
    // No method_exists() check: Filament's base Resource always defines canViewAny().
    actingAs(authzUser(['role' => UserRole::Moderator->value]));
    expect($resource::canViewAny())->toBeTrue($resource.' must stay reachable for a moderator');

    actingAs(authzUser(['role' => UserRole::User->value]));
    expect($resource::canViewAny())->toBeFalse($resource.' must be closed to a plain user');
})->with([
    [CommentResource::class],
    [MediaResource::class],
    [ModerationResource::class],
    [PostResource::class],
    [ReportResource::class],
    [TagResource::class],
    [UserResource::class],
]);
