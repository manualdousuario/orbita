<?php

declare(strict_types=1);

/**
 * The unread notification badge on the header bell.
 */

namespace Tests\Feature\Web;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function userWithUnread(int $n): User
{
    $user = User::factory()->createOne([
        'username' => 'badge_'.uniqid(),
        'email_verified_at' => now(),
    ]);

    for ($i = 0; $i < $n; $i++) {
        Notification::create([
            'user_id' => $user->id,
            'type' => 'comment',
            'title' => 'Notificação '.$i,
            'is_read' => false,
        ]);
    }

    return $user;
}

it('mark all read zeroes the badge in the same request', function () {
    $user = userWithUnread(3);

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->assertSet('unreadCount', 3)
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

it('mark all read is correct when the count was already read', function () {
    $user = userWithUnread(2);

    // Warm once() on the very instance the component will use.
    actingAs($user);
    expect(auth()->user()->unreadNotificationsCount())->toBe(2);

    Livewire::test('notifications-bell')
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

it('the header bell renders the unread count', function () {
    $user = userWithUnread(4);

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->assertSet('unreadCount', 4)
        ->assertSee('4');
});

it('marking one notification read decrements the badge', function () {
    $user = userWithUnread(3);
    $id = (int) Notification::where('user_id', $user->id)->value('id');

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->assertSet('unreadCount', 3)
        ->call('markRead', $id)
        ->assertSet('unreadCount', 2);

    $notification = Notification::find($id);
    expect((bool) $notification?->is_read)->toBeTrue()
        ->and($notification?->read_at)->not->toBeNull();
});

it('marking one notification read clears its dot in the dropdown', function () {
    $user = userWithUnread(2);
    $id = (int) Notification::where('user_id', $user->id)->value('id');

    $component = Livewire::actingAs($user)
        ->test('notifications-bell')
        ->call('loadRecent')
        ->call('markRead', $id);

    $recent = collect($component->get('recent'))->firstWhere('id', $id);

    expect($recent)->not->toBeNull();
    expect($recent['is_read'])->toBeTrue('The dot survives the re-render when $recent is stale.');
});

it('the dropdown renders a mark as read button only for unread rows', function () {
    $user = userWithUnread(1);
    Notification::create([
        'user_id' => $user->id,
        'type' => 'comment',
        'title' => 'Já lida',
        'is_read' => true,
    ]);

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->call('loadRecent')
        ->assertSeeHtml('aria-label="Marcar como lida: Notificação 0"')
        ->assertDontSeeHtml('aria-label="Marcar como lida: Já lida"');
});

it('marking another users notification read is a no op', function () {
    $user = userWithUnread(2);
    $stranger = userWithUnread(1);
    $strangersId = (int) Notification::where('user_id', $stranger->id)->value('id');

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->call('markRead', $strangersId)
        ->assertSet('unreadCount', 2);

    expect((bool) Notification::find($strangersId)?->is_read)->toBeFalse();
});

it('marking the same notification read twice does not go negative', function () {
    $user = userWithUnread(1);
    $id = (int) Notification::where('user_id', $user->id)->value('id');

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->call('markRead', $id)
        ->call('markRead', $id)
        ->assertSet('unreadCount', 0);
});

it('a reader with no notifications gets no badge', function () {
    $user = userWithUnread(0);

    Livewire::actingAs($user)
        ->test('notifications-bell')
        ->assertSet('unreadCount', 0)
        ->assertDontSee('bg-red-600');
});
