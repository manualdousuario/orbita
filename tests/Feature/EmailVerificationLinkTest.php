<?php

declare(strict_types=1);

/**
 * Regression suite for the account activation outage.
 */

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\followingRedirects;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function pendingAccount(array $attributes = []): User
{
    return User::factory()->unverified()->createOne($attributes);
}

/**
 * Build the link exactly as production does — through the notification.
 *
 * A missing URL throws rather than asserting: the value is returned as a string,
 * and expect() would not narrow it for the analyser.
 */
function verificationLinkFor(User $user): string
{
    Notification::fake();

    $user->sendEmailVerificationNotification();

    $url = null;

    Notification::assertSentTo($user, VerifyEmailNotification::class, function ($notification) use ($user, &$url) {
        $url = $notification->toMail($user)->viewData['verificationUrl'] ?? null;

        return true;
    });

    if (! is_string($url)) {
        throw new RuntimeException('A notification did not expose a verification URL.');
    }

    return $url;
}

/** Everything after the host: what a click actually delivers to the app. */
function pathOf(string $url): string
{
    $parts = parse_url($url);

    return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
}

/** Run a callback with the URL root a queue worker would have. */
function asQueueWorker(callable $callback): string
{
    config(['orbita.url' => 'https://orbita.social.br']);
    URL::forceRootUrl('https://orbita.social.br');
    URL::forceScheme('https');

    try {
        return $callback();
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme('http');
    }
}

// ------------------------------------------------------- the two outages

/**
 * Link generated under https, clicked as http on a different host.
 */
it('link survives a host and scheme mismatch', function () {
    $user = pendingAccount();

    // Mint the link the way the queue worker does: root from APP_URL, scheme forced to https.
    $link = asQueueWorker(fn (): string => verificationLinkFor($user));

    expect($link)->toStartWith('https://orbita.social.br/');

    // Click it as http://localhost — neither the scheme nor host the link was signed under.
    get(pathOf($link))->assertRedirect(route('home'));

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
});

/**
 * The same mismatch against an absolutely-signed link reproduces the bug.
 */
it('an absolutely signed link is what breaks under that mismatch', function () {
    $user = pendingAccount();

    $absolute = asQueueWorker(fn (): string => URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes((int) config('auth.verification.expire')),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        absolute: true,
    ));

    expect($absolute)->toStartWith('https://orbita.social.br/');

    // Same path, but the HMAC covered https://orbita.social.br — request arrives as http.
    get(pathOf($absolute))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-invalid');

    expect($user->fresh()?->email_verified_at)->toBeNull();
});

/**
 * A link opened with no session activates the account and signs in.
 */
it('link opened while logged out activates and signs in', function () {
    $user = pendingAccount();

    get(pathOf(verificationLinkFor($user)))
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', 'verification-completed');

    expect($user->fresh()?->email_verified_at)->not->toBeNull();

    assertAuthenticatedAs($user->fresh());
});

// ------------------------------------------------------ signature hygiene

it('link survives appended tracking parameters', function () {
    $user = pendingAccount();

    get(pathOf(verificationLinkFor($user)).'&utm_source=newsletter&fbclid=abc123')
        ->assertRedirect(route('home'));

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
});

it('legacy absolute link is still accepted', function () {
    $user = pendingAccount();

    $legacy = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes((int) config('auth.verification.expire')),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        absolute: true,
    );

    get($legacy)->assertRedirect(route('home'));

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
});

it('generated link is relatively signed and uses the configured host', function () {
    config(['orbita.url' => 'https://orbita.social.br']);

    $link = verificationLinkFor(pendingAccount());

    expect($link)->toStartWith('https://orbita.social.br/email/verify/');

    expect(URL::hasCorrectSignature(Request::create(pathOf($link)), absolute: false))->toBeTrue(
        'The activation link is no longer relatively signed — host and scheme are back in the HMAC.',
    );
});

// ------------------------------------------------- expired vs. tampered

it('genuinely expired link says expired and does not activate', function () {
    $user = pendingAccount();
    $path = pathOf(verificationLinkFor($user));

    travel(25)->hours();

    get($path)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-expired');

    expect($user->fresh()?->email_verified_at)->toBeNull();
});

it('tampered signature is reported as invalid not expired', function () {
    $user = pendingAccount();
    $path = pathOf(verificationLinkFor($user));

    // Flip the last character of the signature.
    $tampered = substr($path, 0, -1).(str_ends_with($path, 'a') ? 'b' : 'a');

    get($tampered)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-invalid');

    expect($user->fresh()?->email_verified_at)->toBeNull();
});

/**
 * Forging an earlier expiry must be reported as invalid, not expired.
 */
it('forged past expiry is invalid not expired', function () {
    $user = pendingAccount();
    $path = pathOf(verificationLinkFor($user));

    $forged = preg_replace('/expires=\d+/', 'expires='.now()->subDay()->getTimestamp(), $path);

    get($forged)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-invalid');

    expect($user->fresh()?->email_verified_at)->toBeNull();
});

// ----------------------------------------------------------- identity

/** Fortify answered this with a bare 403 and no way to recover. */
it('link opened while logged in as another user still activates the target', function () {
    $target = pendingAccount();
    $other = User::factory()->createOne();

    actingAs($other)
        ->get(pathOf(verificationLinkFor($target)))
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', 'verification-completed-other');

    expect($target->fresh()?->email_verified_at)->not->toBeNull();

    assertAuthenticatedAs($other);
});

it('owner clicking their own link lands on the intended url', function () {
    $user = pendingAccount();
    $path = pathOf(verificationLinkFor($user));

    // The `verified` middleware stores url.intended when it bounces a write.
    actingAs($user)->get(route('posts.create'))->assertRedirect(route('verification.notice'));

    actingAs($user)->get($path)
        ->assertRedirect(route('posts.create'))
        ->assertSessionHas('status', 'verification-completed');

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
});

it('second click is idempotent', function () {
    $user = pendingAccount();
    $path = pathOf(verificationLinkFor($user));

    get($path)->assertRedirect(route('home'));
    $verifiedAt = $user->fresh()?->email_verified_at;

    get($path)
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', 'verification-already-active');

    expect($user->fresh()?->email_verified_at)->toEqual($verifiedAt);
});

it('unknown user id is rejected as invalid', function () {
    $link = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 999999, 'hash' => sha1('fantasma@example.com')],
        absolute: false,
    );

    get($link)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-invalid');
});

it('email changed after the link was sent gets its own message', function () {
    $user = pendingAccount(['email' => 'antigo@example.com']);
    $path = pathOf(verificationLinkFor($user));

    $user->forceFill(['email' => 'novo@example.com'])->save();

    get($path)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-stale');

    expect($user->fresh()?->email_verified_at)->toBeNull();
});

// ------------------------------------------------------------- resending

it('notice page is reachable by a guest', function () {
    get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Reenviar email de ativação');
});

it('guest resend with an unknown email is enumeration safe', function () {
    Notification::fake();

    post(route('verification.send'), ['email' => 'nao-existe@example.com'])
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertNothingSent();
});

it('guest resend for an already verified account says the same thing', function () {
    $user = User::factory()->createOne(['email' => 'ativo@example.com']);

    Notification::fake();

    post(route('verification.send'), ['email' => 'ativo@example.com'])
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertNotSentTo($user, VerifyEmailNotification::class);
});

it('guest resend sends to a pending account', function () {
    $user = pendingAccount(['email' => 'pendente@example.com']);

    Notification::fake();

    post(route('verification.send'), ['email' => 'pendente@example.com'])
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

/** An expired link identifies the account, so no retyping is required. */
it('guest can resend after an expired link without typing an email', function () {
    $user = pendingAccount();
    $path = pathOf(verificationLinkFor($user));

    travel(25)->hours();
    get($path)->assertSessionHas('status', 'verification-link-expired');

    Notification::fake();

    post(route('verification.send'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('resend is rate limited per account', function () {
    $user = pendingAccount(['email' => 'insistente@example.com']);

    Notification::fake();

    for ($i = 0; $i < 4; $i++) {
        post(route('verification.send'), ['email' => 'insistente@example.com'])
            ->assertSessionHas('status', 'verification-link-sent');
    }

    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
});

// ----------------------------------------------------------------- views

it('login page never renders a raw status slug', function () {
    withSession(['status' => 'verification-link-expired'])
        ->get(route('login'))
        ->assertOk()
        ->assertDontSee('verification-link-expired');
});

it('success banner is rendered after activation', function () {
    $user = pendingAccount();

    followingRedirects()
        ->get(pathOf(verificationLinkFor($user)))
        ->assertOk()
        ->assertSee('Conta ativada com sucesso!');
});
