<?php

declare(strict_types=1);

/**
 * Editor-toolbar formats and the Markdown::validate() content-gate rules.
 */

namespace Tests\Feature;

use App\Support\Markdown;

// ── spoiler text was removed: ">! !<" is plain text again ────────────────

it('spoiler markers render as plain text', function () {
    $html = Markdown::toHtml('Luke é >!filho do Vader!<, sabia?');

    expect($html)->not->toContain('class="spoiler"');
    expect($html)->toContain('&gt;!filho do Vader!&lt;');
});

it('spoiler markers are not rejected by the content gate', function () {
    expect(Markdown::validate('segredo: >!não leia!< aqui'))->toBe([]);
});

// ── toolbar formats ──────────────────────────────────────────────────────

it('strikethrough renders del', function () {
    expect(Markdown::toHtml('~~riscado~~'))->toContain('<del>riscado</del>');
});

it('task list items render disabled checkboxes', function () {
    $html = Markdown::toHtml("- [ ] pendente\n- [x] feito");

    // TaskList emits bare <li><input> markup; styling hooks on li:has(> input[type=checkbox]).
    expect($html)->toContain('type="checkbox"')
        ->toContain('disabled')
        ->toContain('checked');
});

it('fenced code block renders pre code', function () {
    $html = Markdown::toHtml("```\n\$x = 1;\n```");

    expect($html)->toContain('<pre>')
        ->toContain('<code')
        ->toContain('$x = 1;');
});

it('horizontal rule renders hr', function () {
    expect(Markdown::toHtml("antes\n\n---\n\ndepois"))->toContain('<hr');
});

// ── content gate (Markdown::validate) ────────────────────────────────────

it('code blocks and horizontal rules pass the content gate', function () {
    expect(Markdown::validate("```\ncódigo\n```\n\ntexto\n\n---\n\nmais texto"))->toBe([]);
});

it('strikethrough and checklist pass the content gate', function () {
    expect(Markdown::validate("~~tachado~~\n\n- [ ] tarefa"))->toBe([]);
});

it('atx headings are still rejected', function () {
    expect(Markdown::validate('# Título'))->toBe(['Títulos (headings) não são permitidos.']);
});

it('setext headings are rejected', function () {
    // An underlined prose line would smuggle an <h1>/<h2> past the ATX check.
    expect(Markdown::validate("Título disfarçado\n==="))->toBe(['Títulos (headings) não são permitidos.'])
        ->and(Markdown::validate("Título disfarçado\n---"))->toBe(['Títulos (headings) não são permitidos.']);
});

it('horizontal rule after a blank line is not confused with a heading', function () {
    expect(Markdown::validate("texto\n\n---\n\nmais texto"))->toBe([]);
});
