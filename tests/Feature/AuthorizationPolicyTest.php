<?php

declare(strict_types=1);

/**
 * Locks the consolidated authorization matrix (owner vs staff vs stranger).
 */

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * forceFill: `role` is not fillable on User (privilege changes must be explicit).
 */
function policyUser(string $username, UserRole $role): User
{
    $user = new User;

    $user->forceFill([
        'username' => $username,
        'email' => $username.'@example.com',
        'password' => 'secret123',
        'display_name' => $username,
        'role' => $role->value,
    ])->save();

    return $user;
}

function policyPost(User $author): Post
{
    return Post::create([
        'user_id' => $author->id,
        'hashid' => 'auth'.$author->id,
        'title' => 'T',
        'slug' => 't-'.$author->id,
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function policyComment(User $author, Post $post): Comment
{
    return Comment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'hashid' => 'authc'.$author->id,
        'content' => 'c',
        'status' => 'visible',
        'nesting_level' => 0,
    ]);
}

it('role is cast to the enum and stored as a string', function () {
    $admin = policyUser('cast_admin', UserRole::Admin);

    expect($admin->fresh()?->role)->toBeInstanceOf(UserRole::class)
        ->and($admin->fresh()?->role)->toBe(UserRole::Admin);

    assertDatabaseHas('users', ['username' => 'cast_admin', 'role' => 'admin']);
});

it('is staff and is admin predicates', function () {
    expect(UserRole::Admin->isStaff())->toBeTrue()
        ->and(UserRole::Moderator->isStaff())->toBeTrue()
        ->and(UserRole::User->isStaff())->toBeFalse();

    expect(policyUser('a', UserRole::Admin)->isStaff())->toBeTrue()
        ->and(policyUser('m', UserRole::Moderator)->isStaff())->toBeTrue()
        ->and(policyUser('u', UserRole::User)->isStaff())->toBeFalse();

    expect(policyUser('a2', UserRole::Admin)->isAdmin())->toBeTrue()
        ->and(policyUser('m2', UserRole::Moderator)->isAdmin())->toBeFalse();
});

it('post delete and revisions are owner or staff', function () {
    $owner = policyUser('p_owner', UserRole::User);
    $moderator = policyUser('p_mod', UserRole::Moderator);
    $stranger = policyUser('p_stranger', UserRole::User);
    $post = policyPost($owner);

    foreach (['delete', 'viewRevisions'] as $ability) {
        expect($owner->can($ability, $post))->toBeTrue("owner may $ability")
            ->and($moderator->can($ability, $post))->toBeTrue("staff may $ability")
            ->and($stranger->can($ability, $post))->toBeFalse("stranger may not $ability");
    }
});

it('post update delegates to the service edit window', function () {
    config(['orbita.posts.edit_time_limit' => 900]);

    $owner = policyUser('pu_owner', UserRole::User);
    $moderator = policyUser('pu_mod', UserRole::Moderator);
    $stranger = policyUser('pu_stranger', UserRole::User);
    $post = policyPost($owner);

    // Fresh post: owner is inside the window.
    expect($owner->can('update', $post))->toBeTrue()
        ->and($moderator->can('update', $post))->toBeTrue('staff bypass the window')
        ->and($stranger->can('update', $post))->toBeFalse();

    // Push created_at outside the window: owner loses update, staff still bypass.
    $post->forceFill(['created_at' => now()->subHour()])->save();

    expect($owner->fresh()?->can('update', $post->fresh()))->toBeFalse('owner outside the window')
        ->and($moderator->can('update', $post->fresh()))->toBeTrue('staff still bypass');
});

it('comment delete and view are owner or staff', function () {
    $owner = policyUser('c_owner', UserRole::User);
    $admin = policyUser('c_admin', UserRole::Admin);
    $stranger = policyUser('c_stranger', UserRole::User);
    $comment = policyComment($owner, policyPost($owner));

    foreach (['delete', 'view'] as $ability) {
        expect($owner->can($ability, $comment))->toBeTrue()
            ->and($admin->can($ability, $comment))->toBeTrue()
            ->and($stranger->can($ability, $comment))->toBeFalse();
    }
});

it('account management is owner or admin only', function () {
    $owner = policyUser('acc_owner', UserRole::User);
    $admin = policyUser('acc_admin', UserRole::Admin);
    $moderator = policyUser('acc_mod', UserRole::Moderator);
    $stranger = policyUser('acc_stranger', UserRole::User);

    expect($owner->can('update', $owner))->toBeTrue('owner manages self')
        ->and($admin->can('update', $owner))->toBeTrue('admin manages anyone')
        ->and($moderator->can('update', $owner))->toBeFalse('moderators are not account managers')
        ->and($stranger->can('update', $owner))->toBeFalse();
});

it('the consolidated staff check runs no query', function () {
    $moderator = policyUser('nq_mod', UserRole::Moderator);

    DB::enableQueryLog();
    $moderator->isStaff();

    expect(DB::getQueryLog())->toHaveCount(0, 'isStaff reads the cast attribute, not the database');

    DB::disableQueryLog();
});
