<?php

declare(strict_types=1);

/**
 * Notifications page, unread count, and mark-all-read.
 */

namespace Tests\Feature\Web;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('authed user sees their notifications and correct unread count', function () {
    $user = User::factory()->createOne(['username' => 'notif_'.uniqid(), 'email_verified_at' => now()]);

    Notification::create([
        'user_id' => $user->id,
        'type' => 'comment',
        'title' => 'Novo comentário no seu post',
        'message' => 'Alguém comentou.',
        'link' => '/p/abc',
        'is_read' => false,
    ]);

    Notification::create([
        'user_id' => $user->id,
        'type' => 'mention',
        'title' => 'Você foi mencionado',
        'message' => 'Numa discussão.',
        'is_read' => true,
        'read_at' => now(),
    ]);

    $response = actingAs($user)->get('/notifications');

    $response->assertOk();
    $response->assertSee('Novo comentário no seu post');
    $response->assertSee('Você foi mencionado');
    $response->assertViewHas('unreadCount', 1);
});

it('guest is redirected from notifications', function () {
    get('/notifications')->assertRedirect();
});

it('mark all read zeroes the unread count', function () {
    $user = User::factory()->createOne(['username' => 'notif_'.uniqid(), 'email_verified_at' => now()]);

    Notification::create([
        'user_id' => $user->id,
        'type' => 'system',
        'title' => 'Aviso do sistema',
        'is_read' => false,
    ]);

    actingAs($user)->post('/notifications/read-all')->assertRedirect();

    expect(Notification::where('user_id', $user->id)->where('is_read', false)->count())->toBe(0);
});
