<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAuthStatus;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use App\Support\SocialAuthOutcome;
use App\Support\SocialProfile;
use App\Support\SocialProfileNormalizer;
use App\Support\Url;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Social login, connect, and account-linking flow.
 */
class SocialAuthController extends Controller
{
    /** How long the "complete registration" payload survives in the session. */
    public const PENDING_TTL_MINUTES = 15;

    public const PENDING_SESSION_KEY = 'social.pending';

    public function __construct(private readonly SocialAuthService $social) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $socialProvider = $this->resolveProvider($provider);

        if (filled($redirectTo = $request->query('redirect_to')) && Url::isLocal((string) $redirectTo)) {
            $request->session()->put('url.intended', (string) $redirectTo);
        }

        try {
            return Socialite::driver($socialProvider->driver())->redirect();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', $this->unavailableMessage($socialProvider));
        }
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $socialProvider = $this->resolveProvider($provider);
        $ip = (string) ($request->ip() ?? '0.0.0.0');

        if (! $request->filled('code')) {
            return redirect()->route('login')->with('error', $this->unavailableMessage($socialProvider));
        }

        try {
            $socialiteUser = Socialite::driver($socialProvider->driver())->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')
                ->with('error', 'Sua sessão expirou durante o login. Tente novamente.');
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', $this->unavailableMessage($socialProvider));
        }

        $profile = SocialProfileNormalizer::fromSocialite($socialProvider, $socialiteUser);

        if ($profile->providerUserId === '') {
            return redirect()->route('login')->with('error', $this->unavailableMessage($socialProvider));
        }

        $actor = Auth::user();

        $outcome = $actor !== null
            ? $this->social->handleConnect($actor, $profile, $ip)
            : $this->social->handleLogin($profile, $ip);

        return $this->respond($request, $outcome, $profile, $actor !== null);
    }

    private function resolveProvider(string $provider): SocialProvider
    {
        $resolved = SocialProvider::tryFrom($provider);

        abort_if($resolved === null || ! $resolved->isEnabled() || ! $resolved->isConfigured(), 404);

        return $resolved;
    }

    private function respond(Request $request, SocialAuthOutcome $outcome, SocialProfile $profile, bool $wasConnecting): RedirectResponse
    {
        return match ($outcome->status) {
            SocialAuthStatus::LoggedIn => $this->afterLogin($outcome, $wasConnecting),

            SocialAuthStatus::NeedsVerification => redirect()->route('verification.notice')
                ->with('status', 'verification-required'),

            SocialAuthStatus::NeedsEmail => $this->startCompleteRegistration($request, $profile),

            SocialAuthStatus::LinkPending => redirect()->route('login')
                ->with('success', (string) $outcome->message),

            SocialAuthStatus::Error => $this->errorRedirect($outcome, $wasConnecting),
        };
    }

    private function afterLogin(SocialAuthOutcome $outcome, bool $wasConnecting): RedirectResponse
    {
        if ($wasConnecting) {
            return redirect()
                ->route('users.edit', ['username' => $outcome->user?->username])
                ->with('success', $outcome->message ?? 'Conta conectada.');
        }

        $redirect = redirect()->intended(route('home'));

        return $outcome->message !== null
            ? $redirect->with('success', $outcome->message)
            : $redirect;
    }

    private function startCompleteRegistration(Request $request, SocialProfile $profile): RedirectResponse
    {
        $request->session()->put(self::PENDING_SESSION_KEY, $profile->toArray() + [
            'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route('social.complete');
    }

    private function errorRedirect(SocialAuthOutcome $outcome, bool $wasConnecting): RedirectResponse
    {
        $message = (string) $outcome->message;

        if ($wasConnecting) {
            return redirect()
                ->route('users.edit', ['username' => Auth::user()?->username])
                ->with('error', $message);
        }

        return redirect()->route('login')->with('error', $message);
    }

    private function unavailableMessage(SocialProvider $provider): string
    {
        return 'Não foi possível entrar com o '.$provider->label().' agora. Tente novamente em instantes.';
    }
}
