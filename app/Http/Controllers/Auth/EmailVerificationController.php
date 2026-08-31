<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TurnstileService;
use App\Support\VerificationSignature;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email verification (activation) endpoints.
 */
class EmailVerificationController extends Controller
{
    private const PENDING_KEY = 'verification.pending_user_id';

    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        return view('auth.verify-email', [
            'email' => $user?->getEmailForVerification(),
            'knowsWhoYouAre' => $user !== null || $request->session()->has(self::PENDING_KEY),
        ]);
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $state = VerificationSignature::check($request);

        if ($state === VerificationSignature::INVALID) {
            $this->logRejection($request, 'signature');

            return $this->toNotice('verification-link-invalid');
        }

        if ($state === VerificationSignature::EXPIRED) {
            $request->session()->put(self::PENDING_KEY, (int) $id);

            return $this->toNotice('verification-link-expired');
        }

        $user = ctype_digit($id) ? User::query()->find((int) $id) : null;

        if ($user === null || $user->isAnonymized()) {
            $this->logRejection($request, 'user');

            return $this->toNotice('verification-link-invalid');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            $request->session()->put(self::PENDING_KEY, (int) $user->getKey());

            return $this->toNotice('verification-link-stale');
        }

        if ($user->hasVerifiedEmail()) {
            $request->session()->forget(self::PENDING_KEY);

            return $this->done($request, $user, already: true);
        }

        DB::transaction(function () use ($user): void {
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        });

        $request->session()->forget(self::PENDING_KEY);

        return $this->done($request, $user, already: false);
    }

    public function send(Request $request): RedirectResponse
    {
        $wasAuthenticated = $request->user() !== null;
        $user = $request->user() ?? $this->resolveGuestTarget($request);

        if ($user !== null
            && ! $user->hasVerifiedEmail()
            && ! $user->is_banned
            && ! $user->isAnonymized()
            && RateLimiter::attempt(
                'verification-send:user:'.$user->getKey(),
                maxAttempts: 3,
                callback: static fn (): bool => true,
                decaySeconds: 3600,
            )
        ) {
            $user->sendEmailVerificationNotification();
        }

        return $wasAuthenticated
            ? back()->with('status', 'verification-link-sent')
            : redirect()->route('verification.notice')->with('status', 'verification-link-sent');
    }

    private function resolveGuestTarget(Request $request): ?User
    {
        $pendingId = (int) $request->session()->get(self::PENDING_KEY, 0);

        if ($pendingId > 0) {
            return User::query()->find($pendingId);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $turnstile = app(TurnstileService::class);

        if ($turnstile->isEnabled()
            && ! $turnstile->validate((string) $request->input('cf-turnstile-response', ''), (string) $request->ip())) {
            throw ValidationException::withMessages([
                'email' => ['Verificação de segurança inválida. Por favor, tente novamente.'],
            ]);
        }

        return User::query()->where('email', Str::lower($validated['email']))->first();
    }

    private function toNotice(string $status): RedirectResponse
    {
        return redirect()->route('verification.notice')->with('status', $status);
    }

    private function done(Request $request, User $user, bool $already): RedirectResponse
    {
        if ((int) Auth::id() === (int) $user->getKey()) {
            return redirect()
                ->intended(route('home'))
                ->with('status', $already ? 'verification-already-active' : 'verification-completed');
        }

        if (Auth::check()) {
            return redirect()
                ->route('home')
                ->with('status', 'verification-completed-other')
                ->with('verification.masked_email', self::maskEmail($user->getEmailForVerification()));
        }

        Auth::guard('web')->setRememberDuration((int) config('orbita.auth.remember_minutes'));

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()
            ->route('home')
            ->with('status', $already ? 'verification-already-active' : 'verification-completed');
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).str_repeat('*', max(1, Str::length($local) - 1)).'@'.$domain;
    }

    private function logRejection(Request $request, string $stage): void
    {
        Log::warning('Activation link rejected', [
            'stage' => $stage,
            'url' => $request->fullUrl(),
            'scheme' => $request->getScheme(),
            'secure' => $request->isSecure(),
            'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
            'host_header' => $request->header('Host'),
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'client_ip' => $request->ip(),
            'trusted_proxies' => $request->getTrustedProxies(),
        ]);
    }
}
