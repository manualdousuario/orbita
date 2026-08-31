<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Social\UnlinkSocialAccount;
use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Social account link and unlink endpoints.
 */
class SocialConnectionController extends Controller
{
    public function __construct(private readonly UnlinkSocialAccount $unlink) {}

    public function store(string $username, string $provider): RedirectResponse
    {
        [$user, $socialProvider] = $this->resolve($username, $provider);

        return redirect()->route('social.redirect', ['provider' => $socialProvider->value]);
    }

    public function destroy(string $username, string $provider): RedirectResponse
    {
        [$user, $socialProvider] = $this->resolve($username, $provider);

        [$ok, $message] = $this->unlink->handle($user, $socialProvider);

        return redirect()
            ->route('users.edit', ['username' => $user->username])
            ->with($ok ? 'success' : 'error', $message);
    }

    /** @return array{0: User, 1: SocialProvider} */
    private function resolve(string $username, string $provider): array
    {
        $user = User::query()->where('username', $username)->whereNull('anonymized_at')->firstOrFail();

        abort_unless(Auth::user()?->can('manageConnections', $user) ?? false, 403);

        $socialProvider = SocialProvider::tryFrom($provider);

        abort_if($socialProvider === null || ! $socialProvider->isEnabled() || ! $socialProvider->isConfigured(), 404);

        return [$user, $socialProvider];
    }
}
