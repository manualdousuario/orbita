<?php

declare(strict_types=1);

/**
 * Additive audit trail scoped to sensitive changes (role/ban, settings, taxonomy).
 */

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('a role change is logged with causer and subject', function () {
    $admin = User::factory()->createOne(['username' => 'boss', 'role' => UserRole::Admin->value]);
    $target = User::factory()->createOne(['username' => 'member', 'role' => UserRole::User->value]);

    actingAs($admin);
    Activity::query()->delete(); // ignore the 'created' events from setup

    $target->forceFill(['role' => UserRole::Moderator->value])->save();

    expect(Activity::count())->toBe(1);

    $activity = Activity::first();
    expect($activity->log_name)->toBe('user')
        ->and($activity->subject->is($target))->toBeTrue()
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->properties['attributes']['role'])->toBe('moderator')
        ->and($activity->properties['old']['role'])->toBe('user');
});

it('a ban is logged', function () {
    $target = User::factory()->createOne(['username' => 'spammer', 'is_banned' => false]);
    Activity::query()->delete();

    $target->forceFill(['is_banned' => true])->save();

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->log_name)->toBe('user');
});

it('non sensitive user writes are not logged', function () {
    $user = User::factory()->createOne(['username' => 'reader']);
    Activity::query()->delete();

    $user->update(['display_name' => 'New Name', 'last_login_at' => now()]);

    expect(Activity::count())->toBe(0);
});

it('creating a post is not logged', function () {
    $author = User::factory()->createOne(['username' => 'writer']);
    Activity::query()->delete();

    Post::create([
        'user_id' => $author->id,
        'hashid' => 'al1',
        'title' => 'T',
        'slug' => 't',
        'content' => 'c',
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect(Activity::count())->toBe(0, 'posts are high-volume; not audited');
});

it('a settings change is logged', function () {
    Activity::query()->delete();

    Setting::updateOrCreate(['key' => 'orbita.name'], ['value' => 'Novo Nome']);

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->log_name)->toBe('setting');
});

it('a taxonomy change is logged', function () {
    Activity::query()->delete();

    Term::create(['taxonomy' => 'tag', 'name' => 'Laravel', 'slug' => 'laravel', 'is_active' => true]);

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->log_name)->toBe('term');
});
