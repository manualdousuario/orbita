<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Services\TurnstileService;
use App\Support\Url;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL as UrlGenerator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Fortify authentication configuration.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->registerRateLimiters();
        $this->registerAuthentication();
        $this->registerViewResponses();
        $this->registerLocalizedNotifications();
    }

    /**
     * Localise built-in auth notifications to pt-BR via the shared Blade views.
     */
    private function registerLocalizedNotifications(): void
    {
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            $path = UrlGenerator::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1((string) $notifiable->getEmailForVerification()),
                ],
                absolute: false,
            );

            return rtrim((string) config('orbita.url'), '/').$path;
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $displayName = (string) ($notifiable->display_name
                ?? $notifiable->username
                ?? $notifiable->getEmailForVerification());

            return (new MailMessage)
                ->subject('Confirme seu email - '.config('orbita.name', 'Órbita'))
                ->view(['emails.verify-email', 'emails.text.verify-email'], [
                    'displayName' => $displayName,
                    'verificationUrl' => $url,
                    'preferencesUrl' => rtrim((string) config('orbita.url'), '/')
                        .route('users.edit', ['username' => $notifiable->username], false),
                ]);
        });

        ResetPassword::toMailUsing(function (CanResetPassword $notifiable, string $token): MailMessage {
            $email = (string) $notifiable->getEmailForPasswordReset();

            $resetUrl = rtrim((string) config('orbita.url'), '/').route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], absolute: false);

            $displayName = (string) ($notifiable->display_name ?? $notifiable->username ?? '');

            return (new MailMessage)
                ->subject('Redefinir senha - '.config('orbita.name', 'Órbita'))
                ->view(['emails.reset-password', 'emails.text.reset-password'], [
                    'displayName' => $displayName,
                    'email' => $email,
                    'resetUrl' => $resetUrl,
                ]);
        });
    }

    /**
     * Register the login rate limiter.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes((int) (config('orbita.auth.login_decay_seconds') / 60), (int) config('orbita.auth.max_login_attempts'))
                ->by($this->throttleKey($request));
        });

        RateLimiter::for('verification-verify', fn (Request $request) => [
            Limit::perMinutes(60, 30)->by('vv:ip:'.$request->ip()),
            Limit::perMinutes(60, 12)->by('vv:id:'.$request->route('id')),
        ]);

        RateLimiter::for('verification-send', fn (Request $request) => [
            Limit::perMinutes(60, 8)->by('vs:ip:'.$request->ip()),
            Limit::perMinutes(60, 4)->by('vs:target:'.(
                $request->user()?->getAuthIdentifier()
                    ?? Str::lower(trim((string) $request->input('email')))
            )),
        ]);

        RateLimiter::for('social-redirect', fn (Request $request) => Limit::perMinutes(60, 20)->by('sr:ip:'.$request->ip()));

        RateLimiter::for('social-callback', fn (Request $request) => Limit::perMinutes(60, 20)->by('sc:ip:'.$request->ip()));

        RateLimiter::for('social-complete', fn (Request $request) => [
            Limit::perMinutes(60, 10)->by('scp:ip:'.$request->ip()),
            Limit::perMinutes(60, 5)->by('scp:sess:'.$request->session()->getId()),
        ]);

        RateLimiter::for('images', fn (Request $request) => Limit::perMinute(300)->by('img:ip:'.$request->ip()));

        RateLimiter::for('fortify', function (Request $request): array|Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return match ($request->route()?->getName()) {
                'register.store' => [
                    Limit::perMinutes(60, 5)->by('reg:ip:'.$request->ip()),
                ],
                'password.email' => [
                    Limit::perMinutes(60, 8)->by('pe:ip:'.$request->ip()),
                    Limit::perMinutes(60, 4)->by('pe:target:'.$email),
                ],
                'password.update' => [
                    Limit::perMinutes(60, 10)->by('pu:ip:'.$request->ip()),
                    Limit::perMinutes(60, 6)->by('pu:target:'.$email),
                ],
                default => Limit::none(),
            };
        });
    }

    /**
     * Custom login callback implementing the legacy Órbita login rules.
     */
    private function registerAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $email = (string) $request->input(Fortify::username());
            $ip = (string) $request->ip();
            $key = $this->throttleKey($request);

            // Rule 3 - lock once too many failed attempts accumulate for email + IP.
            if (RateLimiter::tooManyAttempts($key, (int) config('orbita.auth.max_login_attempts'))) {
                $minutes = (int) max(1, ceil(RateLimiter::availableIn($key) / 60));

                throw ValidationException::withMessages([
                    Fortify::username() => ["Muitas tentativas incorretas. Tente novamente em {$minutes} minuto(s)."],
                ]);
            }

            // Rule 2 - Cloudflare Turnstile (only when enabled).
            $turnstile = app(TurnstileService::class);
            if ($turnstile->isEnabled()) {
                $token = (string) $request->input('cf-turnstile-response', '');
                if (! $turnstile->validate($token, $ip)) {
                    throw ValidationException::withMessages([
                        Fortify::username() => ['Verificação de segurança inválida. Por favor, tente novamente.'],
                    ]);
                }
            }

            $user = User::where('email', $email)->first();

            if ($user !== null && $user->isAnonymized()) {
                $user = null;
            }

            if ($user !== null && ! $user->hasPassword()) {
                RateLimiter::hit($key, (int) config('orbita.auth.login_decay_seconds'));

                throw ValidationException::withMessages([
                    Fortify::username() => ['Esta conta usa login social. Entre pelo botão do provedor ou use "Esqueci a senha" para criar uma senha.'],
                ]);
            }

            // Credential check; a miss counts as a failed attempt.
            if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
                RateLimiter::hit($key, (int) config('orbita.auth.login_decay_seconds'));

                return null;
            }

            // Rule 4 - banned users cannot log in.
            if ($user->is_banned) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['Sua conta foi banida'],
                ]);
            }

            // Success: reset the lockout counter and record login metadata.
            RateLimiter::clear($key);

            $longer = $request->boolean('remember') || $request->boolean('remember_me');

            Auth::guard('web')->setRememberDuration((int) config(
                $longer ? 'orbita.auth.remember_minutes' : 'orbita.auth.remember_default_minutes'
            ));

            $request->merge(['remember' => true]);

            // Rule 6 - update last login timestamp and IP.
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ])->save();

            return $user;
        });
    }

    /**
     * Register the Blade views backing Fortify's auth routes.
     */
    private function registerViewResponses(): void
    {
        Fortify::loginView(function (Request $request) {
            $redirectTo = $request->query('redirect_to');

            if (is_string($redirectTo) && Url::isLocal($redirectTo)) {
                session(['url.intended' => $redirectTo]);
            }

            return view('auth.login');
        });
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
    }

    /**
     * Build the rate-limiter key from login email and client IP.
     */
    private function throttleKey(Request $request): string
    {
        return 'login|'.Str::transliterate(
            Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip()
        );
    }
}
