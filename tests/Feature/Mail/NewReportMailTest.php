<?php

declare(strict_types=1);

/**
 * Tests the queued NewReportMail alert sent to active moderators and admins.
 */

namespace Tests\Feature\Mail;

use App\Mail\NewReportMail;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

/**
 * Monotonic across the file, so hashids never collide between tests.
 */
function reportSeq(): int
{
    static $n = 0;

    return ++$n;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function reportStaff(string $role, array $attributes = []): User
{
    return User::factory()->createOne(array_merge([
        'username' => $role.'_'.uniqid(),
        'role' => $role,
        'is_banned' => false,
        'email_verified_at' => now(),
    ], $attributes));
}

function reportedComment(string $content = 'comentário denunciado'): Comment
{
    $post = Post::create([
        'user_id' => User::factory()->createOne(['email_verified_at' => now()])->id,
        'hashid' => 'nr'.reportSeq(),
        'title' => 'Post que hospeda o comentário',
        'slug' => 'post-nr-'.uniqid(),
        'content' => 'corpo',
        'status' => 'published',
        'published_at' => now(),
    ]);

    return Comment::create([
        'post_id' => $post->id,
        'user_id' => $post->user_id,
        'hashid' => 'nrc'.reportSeq(),
        'content' => $content,
        'nesting_level' => 0,
        'status' => 'visible',
    ]);
}

function newReportMail(string $content = 'comentário denunciado', bool $autoHidden = false): NewReportMail
{
    return new NewReportMail(
        targetLabel: 'Comentário',
        postTitle: 'Post que hospeda o comentário',
        targetContent: $content,
        targetAuthor: 'bruno_autor',
        reporterName: 'carla_denunciante',
        reason: 'discurso de ódio',
        contentUrl: 'https://orbita.test/p/abc#comment-def',
        adminUrl: 'https://orbita.test/admin/comments/1/edit',
        reportCount: 3,
        autoHidden: $autoHidden,
    );
}

it('only active staff are alerted', function () {
    Mail::fake();

    $moderator = reportStaff('moderator');
    $admin = reportStaff('admin');
    reportStaff('moderator', ['is_banned' => true]);
    reportStaff('admin', ['anonymized_at' => now()]);
    $plain = User::factory()->createOne(['email_verified_at' => now()]);

    $comment = reportedComment();

    app(ReportService::class)->report((int) $plain->id, 'comment', (int) $comment->id, 'ofensivo');

    Mail::assertQueued(NewReportMail::class, 2);

    foreach ([$moderator, $admin] as $recipient) {
        Mail::assertQueued(
            NewReportMail::class,
            fn (NewReportMail $mail) => $mail->hasTo($recipient->email),
        );
    }

    foreach ([$plain->email] as $address) {
        Mail::assertNotQueued(NewReportMail::class, fn (NewReportMail $mail) => $mail->hasTo($address));
    }
});

it('the alert goes out on the emails queue', function () {
    Mail::fake();

    reportStaff('moderator');
    $reporter = User::factory()->createOne(['email_verified_at' => now()]);
    $comment = reportedComment();

    app(ReportService::class)->report((int) $reporter->id, 'comment', (int) $comment->id);

    Mail::assertQueued(NewReportMail::class, fn (NewReportMail $mail) => $mail->queue === 'emails');
});

it('a repeat report from the same person does not alert again', function () {
    Mail::fake();

    reportStaff('moderator');
    $reporter = User::factory()->createOne(['email_verified_at' => now()]);
    $comment = reportedComment();

    $service = app(ReportService::class);
    $service->report((int) $reporter->id, 'comment', (int) $comment->id);
    $service->report((int) $reporter->id, 'comment', (int) $comment->id);

    Mail::assertQueued(NewReportMail::class, 1);
});

it('the subject carries the siren and the post title', function () {
    newReportMail()->assertHasSubject('🚨 Denúncia: comentário em "Post que hospeda o comentário"');
});

it('both bodies carry the reported content in full', function () {
    // Longer than the 300-char truncation applied elsewhere, so a regression to truncation fails.
    $content = str_repeat('conteúdo denunciado que precisa ser lido inteiro. ', 12);
    $mail = newReportMail($content);

    $mail->assertSeeInHtml(nl2br(e($content)), false);
    $mail->assertSeeInText($content);

    foreach (['carla_denunciante', 'discurso de ódio', 'https://orbita.test/p/abc#comment-def'] as $needle) {
        $mail->assertSeeInHtml($needle);
        $mail->assertSeeInText($needle);
    }
});

it('the auto hide warning only shows when the content was hidden', function () {
    newReportMail(autoHidden: false)->assertDontSeeInHtml('ocultado automaticamente');
    newReportMail(autoHidden: true)->assertSeeInHtml('ocultado automaticamente');
    newReportMail(autoHidden: true)->assertSeeInText('ocultado automaticamente');
});

it('a broken mailer never takes the report button down', function () {
    reportStaff('moderator');
    $reporter = User::factory()->createOne(['email_verified_at' => now()]);
    $comment = reportedComment();

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP indisponível'));

    $report = app(ReportService::class)->report((int) $reporter->id, 'comment', (int) $comment->id, 'ofensivo');

    expect($report)->not->toBeNull();

    assertDatabaseHas('reports', [
        'reportable_type' => 'comment',
        'reportable_id' => $comment->id,
    ]);
});
