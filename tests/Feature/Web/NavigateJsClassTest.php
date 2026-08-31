<?php

declare(strict_types=1);

/**
 * The runtime `js` class on <html> must survive Livewire's navigate.
 */

namespace Tests\Feature\Web;

/** The navigated handler restores the js class. */
it('the navigated handler restores the js class', function () {
    $js = (string) file_get_contents(base_path('resources/js/app.js'));

    expect($js)->toMatch(
        "/livewire:navigated'\s*,\s*\(\)\s*=>\s*\{[^}]*classList\.add\('js'\)/",
        'without this, every wire:navigate drops infinite scroll back to numeric pagination',
    );
});

/** The layout never renders the js class server-side. */
it('the layout does not render the js class server side', function () {
    $layout = (string) file_get_contents(base_path('resources/views/components/layouts/app.blade.php'));

    preg_match('/<html\b[^>]*\bclass="([^"]*)"/', $layout, $matches);

    expect($matches)->not->toBeEmpty('the layout needs an <html> tag with a class');

    // The js class is a runtime switch; in the server HTML it breaks the no-JS fallback.
    expect(preg_split('/\s+/', trim($matches[1])))->not->toContain('js');
});
