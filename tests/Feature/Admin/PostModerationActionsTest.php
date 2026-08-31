<?php

declare(strict_types=1);

/**
 * Moderation actions mounted as both row actions and edit-page header actions.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function moderatedPost(array $overrides = []): Post
{
    return Post::create(array_merge([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => 'mod1',
        'title' => 'Post moderado',
        'slug' => 'post-moderado',
        'content' => 'corpo',
        'status' => 'published',
        'allow_comments' => true,
        'is_pinned' => false,
        'published_at' => now(),
    ], $overrides));
}

it('edit page hides a post and logs it', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_mod'));
    $post = moderatedPost();

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->callAction('hide')
        ->assertHasNoActionErrors();

    expect($post->fresh()?->status)->toBe('hidden');

    assertDatabaseHas('moderation', ['action' => 'hide', 'target_type' => 'post', 'target_id' => $post->id]);
});

it('edit page pins and unpins a post', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_mod'));
    $post = moderatedPost();

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->callAction('pin')
        ->assertHasNoActionErrors();

    expect((bool) $post->fresh()?->is_pinned)->toBeTrue();

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->callAction('unpin')
        ->assertHasNoActionErrors();

    expect((bool) $post->fresh()?->is_pinned)->toBeFalse();

    assertDatabaseHas('moderation', ['action' => 'pin', 'target_id' => $post->id]);
    assertDatabaseHas('moderation', ['action' => 'unpin', 'target_id' => $post->id]);
});

it('edit page locks and unlocks comments', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_mod'));
    $post = moderatedPost();

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->callAction('lock_comments')
        ->assertHasNoActionErrors();

    expect((bool) $post->fresh()?->allow_comments)->toBeFalse();

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->callAction('unlock_comments')
        ->assertHasNoActionErrors();

    expect((bool) $post->fresh()?->allow_comments)->toBeTrue();
});

/** Hiding from the edit page updates the form state. */
it('hiding from the edit page updates the form state', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_mod'));
    $post = moderatedPost();

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->assertFormSet(['status' => 'published', 'allow_comments' => true])
        ->callAction('hide')
        ->assertFormSet(['status' => 'hidden'])
        ->callAction('lock_comments')
        ->assertFormSet(['allow_comments' => false]);
});

/** Only the applicable half of each action pair is visible. */
it('only the applicable half of each pair is visible', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_mod'));
    $post = moderatedPost(['status' => 'hidden', 'is_pinned' => true, 'allow_comments' => false]);

    Livewire::test(EditPost::class, ['record' => $post->id])
        ->assertActionVisible('unhide')
        ->assertActionHidden('hide')
        ->assertActionVisible('unpin')
        ->assertActionHidden('pin')
        ->assertActionVisible('unlock_comments')
        ->assertActionHidden('lock_comments');
});

/** The listing keeps the very same actions after the extraction. */
it('list page still carries the actions', function () {
    Filament::setCurrentPanel('admin');
    actingAs(AdminUsers::admin('admin_mod'));
    $post = moderatedPost();

    Livewire::test(ListPosts::class)
        ->callTableAction('pin', $post)
        ->assertHasNoTableActionErrors();

    expect((bool) $post->fresh()?->is_pinned)->toBeTrue();
});
