<?php

declare(strict_types=1);

/**
 * PHP-assertable accessibility checks on rendered markup.
 */

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function bladeAndCssFiles(): array
{
    $files = [];

    foreach ([resource_path('views'), resource_path('css')] as $root) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(blade\.php|css)$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('every page starts with a skip link that reaches the content', function () {
    $response = get('/');

    $response->assertOk();
    $response->assertSee('Pular para o conteúdo');
    $response->assertSee('href="#conteudo"', false);

    // tabindex="-1" is what moves focus to the content instead of leaving it on <body>.
    $response->assertSee('id="conteudo" tabindex="-1"', false);
});

it('the notification bell puts the unread count in its accessible name', function () {
    $user = User::factory()->createOne();

    actingAs($user);

    Notification::query()->insert([
        [
            'user_id' => $user->id,
            'type' => 'reply',
            'title' => 'Alguém respondeu',
            'is_read' => false,
            'created_at' => now(),
        ],
    ]);

    $response = get('/');

    $response->assertOk();
    $response->assertSee('Notificações — 1 não lida', false);
});

/**
 * Lints views for gray text shades that fail the WCAG AA contrast ratio.
 */
it('no view uses a grey that fails contrast', function () {
    $offenders = [];

    foreach (bladeAndCssFiles() as $file) {
        $source = (string) file_get_contents($file);

        preg_match_all('/(?<![\w-])((?:[a-z0-9-]+:)*)text-gray-(400|500)(?![\w-])/', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            [$whole, $variants, $shade] = $match;
            $isDark = str_contains($variants, 'dark:');

            // gray-400 in light mode: 2.54:1 on white.
            if ($shade === '400' && ! $isDark) {
                $offenders[] = basename($file).' → '.$whole;
            }

            // gray-500 in dark mode: 3.04:1 on gray-800, 3.67:1 on gray-900.
            if ($shade === '500' && $isDark) {
                $offenders[] = basename($file).' → '.$whole;
            }
        }
    }

    expect($offenders)->toBe([], "Contrast below WCAG AA:\n".implode("\n", $offenders));
});
