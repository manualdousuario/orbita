<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function adminDateAdmin(): User
{
    return User::factory()->createOne([
        'username' => 'admin_'.uniqid(),
        'role' => 'admin',
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);
}

function adminDatePostsHtml(): string
{
    return (string) Livewire::test(ListPosts::class)->html();
}

it('renders admin dates in the app locale and switches with it', function () {
    $admin = adminDateAdmin();
    actingAs($admin);
    Filament::setCurrentPanel('admin');

    Post::factory()->createOne([
        'user_id' => $admin->id,
        'title' => 'Post com data real',
        'created_at' => Carbon::create(2026, 9, 15, 10, 41),
    ]);

    expect(adminDatePostsHtml())->toContain('15/09/2026 10:41');

    app()->setLocale('en');

    expect(adminDatePostsHtml())->toContain('09/15/2026 10:41 AM');
});
