<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAuthStatus;
use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use App\Services\TurnstileService;
use App\Support\SocialProfile;
use App\Support\UsernameGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Completes social signup from a pending social profile.
 */
class SocialRegistrationController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $social,
        private readonly TurnstileService $turnstile,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $profile = $this->pendingProfile($request);

        if ($profile === null) {
            return $this->expired();
        }

        return view('auth.complete-registration', [
            'profile' => $profile,
            'suggestedUsername' => $this->suggestUsername($profile),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $this->pendingProfile($request);

        if ($profile === null) {
            return $this->expired();
        }

        if (! config('orbita.register', true)) {
            return redirect()->route('login')->with('error', 'O registro de novas contas está desativado.');
        }

        if ($this->turnstile->isEnabled()) {
            $token = (string) $request->input('cf-turnstile-response', '');

            if (! $this->turnstile->validate($token, (string) ($request->ip() ?? ''))) {
                throw ValidationException::withMessages([
                    'email' => ['Verificação de segurança inválida. Por favor, tente novamente.'],
                ]);
            }
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
        ], [
            'username.regex' => 'O nome de usuário deve conter apenas letras, números e underscore.',
        ]);

        $outcome = $this->social->completeRegistration(
            $profile,
            (string) $validated['email'],
            $validated['username'] ?? null,
            (string) ($request->ip() ?? '0.0.0.0'),
        );

        $request->session()->forget(SocialAuthController::PENDING_SESSION_KEY);

        return match ($outcome->status) {
            SocialAuthStatus::NeedsVerification, SocialAuthStatus::LoggedIn => redirect()
                ->route('verification.notice')
                ->with('status', 'verification-required'),

            SocialAuthStatus::LinkPending => redirect()->route('login')
                ->with('success', (string) $outcome->message),

            default => redirect()->route('login')->with('error', (string) $outcome->message),
        };
    }

    /** Reads and validates the payload stashed by SocialAuthController. */
    private function pendingProfile(Request $request): ?SocialProfile
    {
        $payload = $request->session()->get(SocialAuthController::PENDING_SESSION_KEY);

        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(SocialAuthController::PENDING_SESSION_KEY);

            return null;
        }

        return SocialProfile::fromArray($payload);
    }

    private function suggestUsername(SocialProfile $profile): string
    {
        $seed = $profile->usernameSeed();

        return $seed !== '' ? UsernameGenerator::fromNickname($seed) : '';
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('error', 'Sua sessão de cadastro expirou. Entre novamente pelo provedor.');
    }
}
