<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SocialProvider;
use App\Events\SocialLinkConfirmationRequested;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserToken;
use App\Support\SocialAuthOutcome;
use App\Support\SocialProfile;
use App\Support\UsernameGenerator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Handles the social login flow: log in, register, link, or confirm.
 */
class SocialAuthService
{
    /** Confirmation links live as long as the email-change ones. */
    private const LINK_TOKEN_TTL_HOURS = 24;

    /**
     * The visitor came back from the provider while logged out: log in, register, or ask for confirmation. Steps 3-6 of the flow.
     */
    public function handleLogin(SocialProfile $profile, string $ip): SocialAuthOutcome
    {
        $existing = $this->findIdentity($profile);

        if ($existing !== null) {
            return $this->loginExistingIdentity($existing, $profile, $ip);
        }

        if (blank($profile->email)) {
            return SocialAuthOutcome::needsEmail();
        }

        $local = $this->findLocalAccountByEmail((string) $profile->email);

        if ($local !== null) {
            return $this->attachToLocalAccount($local, $profile, $ip);
        }

        return $this->registerNewUser($profile, (string) $profile->email, null, $ip);
    }

    /**
     * The authenticated visitor connects a new provider account.
     */
    public function handleConnect(User $actor, SocialProfile $profile, string $ip): SocialAuthOutcome
    {
        $label = $profile->provider->label();
        $existing = $this->findIdentity($profile);

        if ($existing !== null) {
            return (int) $existing->user_id === (int) $actor->getKey()
                ? SocialAuthOutcome::loggedIn($actor, 'Sua conta do '.$label.' já estava conectada.')
                : SocialAuthOutcome::error('Esta conta do '.$label.' já está vinculada a outro usuário do '.config('orbita.name', 'Órbita').'.');
        }

        if ($this->hasProvider($actor, $profile->provider)) {
            return SocialAuthOutcome::error('Você já tem uma conta do '.$label.' conectada. Desconecte-a antes de conectar outra.');
        }

        $this->link($actor, $profile, $ip);

        return SocialAuthOutcome::loggedIn($actor, 'Conta do '.$label.' conectada.');
    }

    /**
     * Completes registration with a form-supplied email, pending until verified.
     */
    public function completeRegistration(SocialProfile $profile, string $email, ?string $username, string $ip): SocialAuthOutcome
    {
        if ($this->findIdentity($profile) !== null) {
            return SocialAuthOutcome::error('Esta conta do '.$profile->provider->label().' já foi vinculada.');
        }

        $email = Str::lower(trim($email));
        $local = $this->findLocalAccountByEmail($email);

        if ($local !== null) {
            // Deliberately never auto-links and never authenticates, even if the provider claimed
            // the address was verified: the address came from a form, not from the provider.
            return $this->requestLinkConfirmation($local, $profile, $ip);
        }

        return $this->registerNewUser($profile, $email, $username, $ip, forcePending: true);
    }

    /**
     * Attaches the identity and logs the owner in when the confirmation link is clicked.
     */
    public function confirmLink(UserToken $token, string $ip): SocialAuthOutcome
    {
        $profile = SocialProfile::fromArray((array) ($token->payload ?? []));

        if ($profile === null) {
            return SocialAuthOutcome::error('Link inválido.');
        }

        $user = User::query()->find($token->user_id);

        if ($user === null || $user->isAnonymized()) {
            return SocialAuthOutcome::error('Link inválido.');
        }

        if ($user->is_banned) {
            return SocialAuthOutcome::error('Sua conta foi banida');
        }

        $label = $profile->provider->label();
        $existing = $this->findIdentity($profile);

        if ($existing !== null && (int) $existing->user_id !== (int) $user->getKey()) {
            return SocialAuthOutcome::error('Esta conta do '.$label.' já foi vinculada a outro usuário.');
        }

        if ($existing === null && $this->hasProvider($user, $profile->provider)) {
            return SocialAuthOutcome::error('Você já tem uma conta do '.$label.' conectada. Desconecte-a antes de conectar outra.');
        }

        $claimed = false;

        DB::transaction(function () use ($user, $profile, $ip, $token, &$claimed): void {
            if (! $token->consume()) {
                return;
            }

            $claimed = true;
            $now = Carbon::now();

            $this->link($user, $profile, $ip);

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => $now])->save();
            }

            UserToken::query()
                ->where('user_id', (int) $user->getKey())
                ->where('token_type', 'social_link')
                ->whereKeyNot($token->getKey())
                ->where('is_used', false)
                ->update(['is_used' => true, 'used_at' => $now]);
        });

        if (! $claimed) {
            return SocialAuthOutcome::error('Este link já foi utilizado.');
        }

        $this->authenticate($user, $ip);

        return SocialAuthOutcome::loggedIn($user->refresh(), 'Conta do '.$label.' conectada.');
    }

    /**
     * Logs in through a known identity, leaving pending accounts gated by middleware.
     */
    private function loginExistingIdentity(SocialAccount $account, SocialProfile $profile, string $ip): SocialAuthOutcome
    {
        $user = $account->user;

        if ($user === null || $user->isAnonymized()) {
            $account->delete();

            return SocialAuthOutcome::error('Esta conta não está mais disponível.');
        }

        if ($user->is_banned) {
            return SocialAuthOutcome::error('Sua conta foi banida');
        }

        $this->refreshSnapshot($account, $profile);
        $this->authenticate($user, $ip);

        return $user->isPendingActivation()
            ? SocialAuthOutcome::needsVerification($user)
            : SocialAuthOutcome::loggedIn($user);
    }

    /**
     * Links to a local account only when both the provider and the account verified the address.
     */
    private function attachToLocalAccount(User $local, SocialProfile $profile, string $ip): SocialAuthOutcome
    {
        if ($local->is_banned) {
            return SocialAuthOutcome::error('Sua conta foi banida');
        }

        if ($this->hasProvider($local, $profile->provider)) {
            return SocialAuthOutcome::error(
                'Já existe uma conta com esse email, e ela tem outra conta do '.$profile->provider->label().' conectada.'
            );
        }

        if ($profile->emailVerified && $local->email_verified_at !== null) {
            $this->link($local, $profile, $ip);
            $this->authenticate($local, $ip);

            return SocialAuthOutcome::loggedIn($local, 'Conta do '.$profile->provider->label().' conectada.');
        }

        return $this->requestLinkConfirmation($local, $profile, $ip);
    }

    /** Issues the token, emails the account owner, and authenticates nobody. */
    private function requestLinkConfirmation(User $local, SocialProfile $profile, string $ip): SocialAuthOutcome
    {
        if ($local->is_banned) {
            return SocialAuthOutcome::error('Sua conta foi banida');
        }

        $this->issueLinkToken($local, $profile, $ip);

        return SocialAuthOutcome::linkPending(
            'Já existe uma conta com esse email. Enviamos um link para '.$this->maskEmail((string) $local->email)
            .' para confirmar a conexão com o '.$profile->provider->label().'.'
        );
    }

    /**
     * Creates a social-only account (null password) and links the identity.
     */
    private function registerNewUser(SocialProfile $profile, string $email, ?string $username, string $ip, bool $forcePending = false): SocialAuthOutcome
    {
        if (! config('orbita.register', true)) {
            return SocialAuthOutcome::error('O registro de novas contas está desativado.');
        }

        $mustConfirm = $forcePending
            || $profile->provider->requiresEmailConfirmation()
            || ! $profile->emailVerified;

        $user = DB::transaction(function () use ($profile, $email, $username, $ip, $mustConfirm): User {
            $resolvedUsername = filled($username)
                ? UsernameGenerator::unique((string) $username)
                : $this->generateUsername($profile, $email);

            $user = new User;
            $user->forceFill([
                'username' => $resolvedUsername,
                'email' => $email,
                'display_name' => $profile->name ?: $resolvedUsername,
                'password' => null,
                'email_verified_at' => $mustConfirm ? null : Carbon::now(),
            ])->save();

            $this->link($user, $profile, $ip);

            return $user;
        });

        event(new Registered($user));

        $this->authenticate($user, $ip);

        return $mustConfirm
            ? SocialAuthOutcome::needsVerification($user)
            : SocialAuthOutcome::loggedIn($user);
    }

    /** Creates or refreshes the identity row. Idempotent, so retries and re-links are harmless. */
    private function link(User $user, SocialProfile $profile, string $ip): SocialAccount
    {
        return SocialAccount::updateOrCreate(
            [
                'provider' => $profile->provider,
                'provider_user_id' => $profile->providerUserId,
            ],
            [
                'user_id' => (int) $user->getKey(),
                'provider_email' => $profile->email,
                'provider_email_verified' => $profile->emailVerified,
                'provider_nickname' => $profile->nickname,
                'provider_name' => $profile->name,
                'provider_avatar_url' => $profile->avatarUrl,
                'last_login_at' => Carbon::now(),
                'created_ip' => $ip,
            ],
        );
    }

    private function issueLinkToken(User $user, SocialProfile $profile, string $ip): void
    {
        $token = Str::random(64);
        $now = Carbon::now();

        DB::transaction(function () use ($user, $profile, $ip, $token, $now): void {
            // Supersede anything still outstanding, so an old link cannot attach a stale identity.
            UserToken::query()
                ->where('user_id', (int) $user->getKey())
                ->where('token_type', 'social_link')
                ->where('is_used', false)
                ->update(['is_used' => true, 'used_at' => $now]);

            UserToken::create([
                'user_id' => (int) $user->getKey(),
                'token' => UserToken::hashToken($token),
                'token_type' => 'social_link',
                'email' => (string) $user->email,
                'payload' => $profile->toArray(),
                'is_used' => false,
                'request_ip' => $ip,
                'expires_at' => $now->copy()->addHours(self::LINK_TOKEN_TTL_HOURS),
            ]);
        });

        $allowed = RateLimiter::attempt(
            'social-link:target:'.sha1(Str::lower((string) $user->email)),
            3,
            fn () => null,
            3600,
        );

        if ($allowed !== false) {
            event(new SocialLinkConfirmationRequested($user, $profile->provider, $token));
        }
    }

    private function findIdentity(SocialProfile $profile): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('provider', $profile->provider->value)
            ->where('provider_user_id', $profile->providerUserId)
            ->first();
    }

    /**
     * Finds a local account by email; anonymized addresses never match.
     */
    private function findLocalAccountByEmail(string $email): ?User
    {
        return User::query()->where('email', Str::lower($email))->first();
    }

    private function hasProvider(User $user, SocialProvider $provider): bool
    {
        return $user->socialAccounts()->where('provider', $provider->value)->exists();
    }

    private function refreshSnapshot(SocialAccount $account, SocialProfile $profile): void
    {
        $account->forceFill([
            'provider_email' => $profile->email,
            'provider_email_verified' => $profile->emailVerified,
            'provider_nickname' => $profile->nickname,
            'provider_name' => $profile->name,
            'provider_avatar_url' => $profile->avatarUrl,
            'last_login_at' => Carbon::now(),
        ])->save();
    }

    private function generateUsername(SocialProfile $profile, string $email): string
    {
        $seed = $profile->usernameSeed();

        return $seed !== ''
            ? UsernameGenerator::fromNickname($seed)
            : UsernameGenerator::fromEmail($email);
    }

    private function authenticate(User $user, string $ip): void
    {
        Auth::guard('web')->setRememberDuration((int) config('orbita.auth.remember_minutes'));

        Auth::login($user, remember: true);
        session()->regenerate();

        $user->forceFill([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ip,
        ])->save();
    }

    /** j***@example.com — enough to recognise your own address, not enough to learn someone's. */
    private function maskEmail(string $email): string
    {
        $local = Str::before($email, '@');
        $domain = Str::after($email, '@');

        return Str::substr($local, 0, 1).str_repeat('*', max(3, Str::length($local) - 1)).'@'.$domain;
    }
}
