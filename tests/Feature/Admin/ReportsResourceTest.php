<?php

declare(strict_types=1);

/**
 * The admin reports ("Denúncias") triage resource.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function reportsAdmin(): User
{
    return User::factory()->create([
        'username' => 'admin_'.uniqid(),
        'role' => 'admin',
        'is_banned' => false,
        'email_verified_at' => now(),
    ]);
}

/**
 * Monotonic across the whole file, so hashids never collide between tests.
 */
function reportsSeq(): int
{
    static $n = 0;

    return ++$n;
}

function reportFor(User $reporter, ?string $reason = 'spam'): Report
{
    $post = Post::create([
        'user_id' => $reporter->id,
        'hashid' => 'ar'.reportsSeq(),
        'title' => 'Post denunciado',
        'slug' => 'post-denunciado-'.uniqid(),
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now(),
    ]);

    return Report::create([
        'user_id' => $reporter->id,
        'reportable_type' => 'post',
        'reportable_id' => $post->id,
        'reason' => $reason,
    ]);
}

it('admin can load the reports list page', function () {
    actingAs(reportsAdmin())
        ->get('/admin/reports')
        ->assertOk();
});

it('the navigation badge counts only unread reports', function () {
    $reporter = User::factory()->create(['email_verified_at' => now()]);
    reportFor($reporter);
    reportFor($reporter);
    $read = reportFor($reporter);
    $read->update(['read_at' => now(), 'read_by' => $reporter->id]);

    expect(ReportResource::getNavigationBadge())->toBe('2');
    expect(ReportResource::getNavigationBadgeColor())->toBe('warning');

    Report::query()->update(['read_at' => now()]);
    expect(ReportResource::getNavigationBadge())->toBeNull('no unread reports, no badge');
});

it('the unread tab is the default and hides read reports', function () {
    Filament::setCurrentPanel('admin');
    $admin = reportsAdmin();
    $reporter = User::factory()->create(['email_verified_at' => now()]);
    $unread = reportFor($reporter);
    $read = reportFor($reporter);
    $read->update(['read_at' => now(), 'read_by' => $admin->id]);

    actingAs($admin);

    Livewire::test(ListReports::class)
        ->assertCanSeeTableRecords([$unread])
        ->assertCanNotSeeTableRecords([$read])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$unread, $read])
        ->set('activeTab', 'read')
        ->assertCanSeeTableRecords([$read])
        ->assertCanNotSeeTableRecords([$unread]);
});

it('mark read and unread actions drive the triage', function () {
    Filament::setCurrentPanel('admin');
    $admin = reportsAdmin();
    $reporter = User::factory()->create(['email_verified_at' => now()]);
    $report = reportFor($reporter);

    actingAs($admin);

    Livewire::test(ListReports::class)
        ->assertTableActionVisible('mark_read', $report)
        ->assertTableActionHidden('mark_unread', $report)
        ->callTableAction('mark_read', $report);

    $report->refresh();
    expect($report->isRead())->toBeTrue();
    expect((int) $report->read_by)->toBe((int) $admin->id);

    Livewire::test(ListReports::class)
        ->set('activeTab', 'read')
        ->assertTableActionVisible('mark_unread', $report)
        ->callTableAction('mark_unread', $report);

    expect($report->refresh()->isRead())->toBeFalse();
    expect($report->read_by)->toBeNull();
});

it('the bulk action marks everything read', function () {
    Filament::setCurrentPanel('admin');
    $admin = reportsAdmin();
    $reporter = User::factory()->create(['email_verified_at' => now()]);
    $reports = [reportFor($reporter), reportFor($reporter), reportFor($reporter)];

    actingAs($admin);

    Livewire::test(ListReports::class)
        ->callTableBulkAction('mark_read', $reports);

    foreach ($reports as $report) {
        expect($report->refresh()->isRead())->toBeTrue();
    }
});

it('the list shows the target author and the post it belongs to', function () {
    Filament::setCurrentPanel('admin');
    $admin = reportsAdmin();
    $author = User::factory()->create(['username' => 'autor_do_texto', 'email_verified_at' => now()]);
    $reporter = User::factory()->create(['email_verified_at' => now()]);

    $post = Post::create([
        'user_id' => $author->id,
        'hashid' => 'arc'.reportsSeq(),
        'title' => 'Post que hospeda o comentário',
        'slug' => 'post-hospeda-'.uniqid(),
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now(),
    ]);

    // Longer than the old 60-char limit so the whole text must render.
    $content = str_repeat('texto denunciado que precisa ser lido inteiro. ', 5);

    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'hashid' => 'arcc'.reportsSeq(),
        'content' => $content,
        'nesting_level' => 0,
        'status' => 'visible',
    ]);

    $report = Report::create([
        'user_id' => $reporter->id,
        'reportable_type' => 'comment',
        'reportable_id' => $comment->id,
        'reason' => 'ofensivo',
    ]);

    actingAs($admin);

    Livewire::test(ListReports::class)
        ->assertTableColumnStateSet('target_excerpt', $content, $report)
        ->assertTableColumnStateSet('target_author', 'autor_do_texto', $report)
        ->assertTableColumnStateSet('target_post', 'Post que hospeda o comentário', $report);
});

it('reports cannot be created edited or deleted from the panel', function () {
    $reporter = User::factory()->create(['email_verified_at' => now()]);

    expect(ReportResource::canCreate())->toBeFalse();
    expect(ReportResource::canEdit(reportFor($reporter)))->toBeFalse();
    expect(ReportResource::canDelete(reportFor($reporter)))->toBeFalse();
});
