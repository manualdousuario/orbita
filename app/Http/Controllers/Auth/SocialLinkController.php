<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserToken;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Confirms a pending social link via email token.
 */
class SocialLinkController extends Controller
{
    public function __construct(private readonly SocialAuthService $social) {}

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $userToken = UserToken::wherePlainToken($token)
            ->where('token_type', 'social_link')
            ->first();

        if ($userToken === null) {
            return $this->failure('Link inválido.');
        }

        if ($userToken->is_used) {
            return $this->failure('Este link já foi utilizado.');
        }

        if ($userToken->expires_at !== null && $userToken->expires_at->isPast()) {
            return $this->failure('Este link expirou.');
        }

        $outcome = $this->social->confirmLink($userToken, (string) ($request->ip() ?? '0.0.0.0'));

        if ($outcome->failed()) {
            return $this->failure((string) $outcome->message);
        }

        return redirect()
            ->route('users.edit', ['username' => $outcome->user?->username])
            ->with('success', $outcome->message ?? 'Conta conectada.');
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
