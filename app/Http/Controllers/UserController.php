<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AnonymizeUser;
use App\Actions\SetUserPassword;
use App\Enums\SocialProvider;
use App\Enums\UserRole;
use App\Events\AccountDeletionRequested;
use App\Events\EmailChangeRequested;
use App\Exceptions\InvalidImageException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserToken;
use App\Services\ImageService;
use App\Services\MetaTagsService;
use App\Support\Avatar;
use App\Support\LinkGuard;
use App\Support\ReferralLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * User profiles, settings, and account management.
 */
class UserController extends Controller
{
    /**
     * GET /u/{username} — public profile with the user's posts and comments.
     */
    public function profile(string $username): View
    {
        $user = User::query()->where('username', $username)->whereNull('anonymized_at')->firstOrFail();

        $postsTotal = $user->posts()
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->count();

        $meta = app(MetaTagsService::class)->forProfile($user, $postsTotal);

        return view('users.profile', [
            'user' => $user,
            'postsTotal' => $postsTotal,
            'meta' => $meta,
        ]);
    }

    /**
     * GET /mention-search?q=... — typeahead for the editor's @mention autocomplete.
     */
    public function mentionSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $q = trim((string) $validated['q']);
        if ($q === '') {
            return response()->json([]);
        }

        $prefix = $q.'%';
        $contains = '%'.$q.'%';

        $users = User::query()
            ->select(['id', 'username', 'display_name', 'avatar_url'])
            ->where('is_banned', false)
            ->whereNull('anonymized_at')
            ->where(function ($query) use ($prefix, $contains): void {
                $query->where('username', 'like', $prefix)
                    ->orWhere('display_name', 'like', $contains);
            })
            ->orderByRaw(
                'CASE WHEN username = ? THEN 0 WHEN username LIKE ? THEN 1 WHEN display_name LIKE ? THEN 2 ELSE 3 END',
                [$q, $prefix, $prefix]
            )
            ->orderBy('username')
            ->limit(8)
            ->get();

        return response()->json($users->map(fn (User $user): array => [
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_url' => filled($user->avatar_url)
                ? $user->avatar_url
                : Avatar::url((string) $user->username),
        ])->all());
    }

    public function edit(string $username): View
    {
        $user = $this->resolveManageable($username);

        $actor = Auth::user();
        $isSelfEdit = $actor !== null && (int) $actor->id === (int) $user->id;

        return view('users.edit', [
            'user' => $user,
            'usernameLockedUntil' => $isSelfEdit ? $user->usernameChangeAvailableAt() : null,
            'socialAccounts' => $user->socialAccounts()->get()->keyBy(
                fn (SocialAccount $account): string => $account->provider->value
            ),
            'socialProviders' => SocialProvider::available(),
            'isSelfEdit' => $isSelfEdit,
        ]);
    }

    /**
     * PUT /u/{username} — update profile fields, avatar and notification prefs.
     */
    public function update(Request $request, ImageService $images, string $username): RedirectResponse
    {
        $user = $this->resolveManageable($username);

        $actor = Auth::user();
        $isSelfEdit = $actor !== null && (int) $actor->id === (int) $user->id;

        $validated = $request->validate([
            'username' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique(User::class, 'username')->ignore($user->id)],
            'display_name' => ['sometimes', 'required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:'.config('orbita.images.avatar_max_size')],
            'notify_replies_email' => ['nullable', 'boolean'],
            'notify_replies_system' => ['nullable', 'boolean'],
            'notify_mentions_email' => ['nullable', 'boolean'],
            'notify_mentions_system' => ['nullable', 'boolean'],
            'notify_follows_email' => ['nullable', 'boolean'],
            'notify_follows_system' => ['nullable', 'boolean'],
            'default_comment_sort' => ['nullable', 'string', Rule::in(['newest', 'oldest', 'most_reactions'])],
        ], [
            'username.regex' => 'O nome de usuário deve conter apenas letras, números e underscore.',
        ]);

        if (array_key_exists('username', $validated) && $validated['username'] !== $user->username) {
            if ($isSelfEdit && ! $user->canChangeUsername()) {
                $when = $user->usernameChangeAvailableAt()->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY');

                return back()->withInput()->withErrors([
                    'username' => 'Você só pode alterar o nome de usuário uma vez a cada '.config('orbita.auth.username_change_cooldown_days')." dias. Próxima troca disponível em {$when}.",
                ]);
            }

            $user->username = $validated['username'];
            $user->username_changed_at = Carbon::now();
        }

        if (array_key_exists('display_name', $validated)) {
            $user->display_name = $validated['display_name'];
        }

        $stripped = [];

        if ($request->has('bio')) {
            $sanitized = ReferralLinks::cleanText((string) $validated['bio']);
            $user->bio = $validated['bio'] === null ? null : $sanitized['content'];
            $stripped = $sanitized['stripped'];
        }

        if ($request->has('website')) {
            $sanitized = ReferralLinks::cleanLink($validated['website']);
            $user->website = $sanitized['url'];
            $stripped = array_merge($stripped, $sanitized['stripped']);
        }

        if ($request->hasAny(['notify_replies_email', 'notify_replies_system', 'notify_mentions_email', 'notify_mentions_system', 'notify_follows_email', 'notify_follows_system'])) {
            $user->notify_replies_email = $request->boolean('notify_replies_email');
            $user->notify_replies_system = $request->boolean('notify_replies_system');
            $user->notify_mentions_email = $request->boolean('notify_mentions_email');
            $user->notify_mentions_system = $request->boolean('notify_mentions_system');
            $user->notify_follows_email = $request->boolean('notify_follows_email');
            $user->notify_follows_system = $request->boolean('notify_follows_system');
        }

        if ($request->has('default_comment_sort')) {
            $user->default_comment_sort = $request->input('default_comment_sort') ?: null;
        }

        if ($request->hasFile('avatar')) {
            try {
                $user->avatar_url = $images->uploadAvatar($request->file('avatar'), (int) $user->id);
            } catch (InvalidImageException $e) {
                return back()->withInput()->withErrors(['avatar' => $e->getMessage()]);
            }
        }

        $user->save();

        ReferralLinks::report($user, array_values(array_unique($stripped)));
        LinkGuard::report($user, (string) $user->bio, $user->website);

        return redirect()
            ->route('users.edit', ['username' => $user->username])
            ->with('success', 'Perfil atualizado com sucesso.');
    }

    /**
     * PUT /u/{username}/email — issue an email change verification token.
     */
    public function updateEmail(Request $request, string $username): RedirectResponse
    {
        $user = $this->resolveManageable($username);

        $requiresPassword = $user->hasPassword();

        $validated = $request->validate([
            'new_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $requiresPassword ? ['required', 'string'] : ['nullable', 'string'],
        ]);

        if ($requiresPassword && ! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'A senha informada está incorreta.',
            ])->errorBag('updateEmail');
        }

        $token = Str::random(64);

        UserToken::create([
            'user_id' => (int) $user->id,
            'token' => UserToken::hashToken($token),
            'token_type' => 'email_change',
            'email' => $validated['new_email'],
            'is_used' => false,
            'request_ip' => (string) ($request->ip() ?? '0.0.0.0'),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        event(new EmailChangeRequested($user, $validated['new_email'], $token));

        return redirect()
            ->route('users.edit', ['username' => $user->username])
            ->with('success', 'Enviamos um link de confirmação para o novo endereço de email.');
    }

    /**
     * GET /verify-email-change/{token} — confirm a pending email change.
     */
    public function confirmEmailChange(string $token): RedirectResponse
    {
        $userToken = UserToken::wherePlainToken($token)
            ->where('token_type', 'email_change')
            ->first();

        if ($userToken === null) {
            return $this->tokenError('Link inválido.');
        }

        if ($userToken->is_used) {
            return $this->tokenError('Este link já foi utilizado.');
        }

        if ($userToken->expires_at !== null && $userToken->expires_at->isPast()) {
            return $this->tokenError('Este link expirou.');
        }

        $user = User::query()->find($userToken->user_id);

        if ($user === null) {
            return $this->tokenError('Link inválido.');
        }

        $takenByOther = User::query()
            ->where('email', $userToken->email)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($takenByOther) {
            return $this->tokenError('Este email já está em uso.');
        }

        $claimed = false;

        DB::transaction(function () use ($user, $userToken, &$claimed): void {
            if (! $userToken->consume()) {
                return;
            }

            $claimed = true;
            $now = Carbon::now();

            $user->email = $userToken->email;
            $user->email_verified_at = $now;
            $user->save();

            UserToken::query()
                ->where('user_id', (int) $user->id)
                ->where('token_type', 'email_change')
                ->whereKeyNot($userToken->getKey())
                ->where('is_used', false)
                ->update(['is_used' => true, 'used_at' => $now]);
        });

        if (! $claimed) {
            return $this->tokenError('Este link já foi utilizado.');
        }

        if (Auth::check()) {
            return redirect()
                ->route('users.edit', ['username' => $user->username])
                ->with('success', 'Email atualizado com sucesso.');
        }

        return redirect()
            ->route('login')
            ->with('success', 'Email atualizado com sucesso.');
    }

    public function updatePassword(Request $request, UpdatesUserPasswords $updater, string $username): RedirectResponse
    {
        $user = $this->resolveManageable($username);

        if (! $user->hasPassword()) {
            app(SetUserPassword::class)->set($user, $request->all());

            return redirect()
                ->route('users.edit', ['username' => $user->username])
                ->with('success', 'Senha definida com sucesso. Agora você também pode entrar com email e senha.');
        }

        $updater->update($user, $request->all());

        return redirect()
            ->route('users.edit', ['username' => $user->username])
            ->with('success', 'Senha alterada com sucesso.');
    }

    public function requestAccountDeletion(Request $request, string $username): RedirectResponse
    {
        $user = $this->resolveManageable($username);

        abort_unless((int) Auth::id() === (int) $user->id, 403);

        $requiresPassword = $user->hasPassword();

        $validated = $request->validate([
            'password' => $requiresPassword ? ['required', 'string'] : ['nullable', 'string'],
        ]);

        if ($requiresPassword && ! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'A senha informada está incorreta.',
            ])->errorBag('deleteAccount');
        }

        if ($user->isAdmin() && ! $this->anotherAdminExists($user)) {
            return redirect()
                ->route('users.edit', ['username' => $user->username])
                ->with('error', 'Você é o único administrador. Promova outro administrador antes de excluir sua conta.');
        }

        $token = Str::random(64);

        UserToken::query()
            ->where('user_id', (int) $user->id)
            ->where('token_type', 'account_deletion')
            ->where('is_used', false)
            ->update(['is_used' => true, 'used_at' => Carbon::now()]);

        UserToken::create([
            'user_id' => (int) $user->id,
            'token' => UserToken::hashToken($token),
            'token_type' => 'account_deletion',
            'email' => (string) $user->email,
            'is_used' => false,
            'request_ip' => (string) ($request->ip() ?? '0.0.0.0'),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        event(new AccountDeletionRequested($user, $token));

        return redirect()
            ->route('users.edit', ['username' => $user->username])
            ->with('success', 'Enviamos um link de confirmação para '.$user->email.'. Sua conta só será excluída depois que você confirmar por lá.');
    }

    public function showAccountDeletionConfirmation(string $token): View|RedirectResponse
    {
        $userToken = $this->resolveAccountDeletionToken($token);

        if ($userToken instanceof RedirectResponse) {
            return $userToken;
        }

        return view('users.confirm-deletion', [
            'token' => $token,
            'user' => User::query()->find($userToken->user_id),
        ]);
    }

    public function confirmAccountDeletion(Request $request, AnonymizeUser $anonymize, string $token): RedirectResponse
    {
        $userToken = $this->resolveAccountDeletionToken($token);

        if ($userToken instanceof RedirectResponse) {
            return $userToken;
        }

        $user = User::query()->find($userToken->user_id);

        if ($user === null) {
            return $this->tokenError('Link inválido.');
        }

        if ($user->isAdmin() && ! $this->anotherAdminExists($user)) {
            return $this->tokenError('Você é o único administrador. Promova outro administrador antes de excluir sua conta.');
        }

        $wasSelf = (int) Auth::id() === (int) $user->id;

        $anonymize->handle($user, (int) $user->id, $userToken);

        if ($wasSelf) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('login')
            ->with('success', 'Sua conta foi excluída. Suas publicações continuam no site, agora sem identificação.');
    }

    private function resolveAccountDeletionToken(string $token): UserToken|RedirectResponse
    {
        $userToken = UserToken::wherePlainToken($token)
            ->where('token_type', 'account_deletion')
            ->first();

        if ($userToken === null) {
            return $this->tokenError('Link inválido.');
        }

        if ($userToken->is_used) {
            return $this->tokenError('Este link já foi utilizado.');
        }

        if ($userToken->expires_at !== null && $userToken->expires_at->isPast()) {
            return $this->tokenError('Este link expirou.');
        }

        $user = User::query()->find($userToken->user_id);

        if ($user === null || $user->isAnonymized()) {
            return $this->tokenError('Link inválido.');
        }

        return $userToken;
    }

    /** Whether some other, still-active admin would remain if this one went away. */
    private function anotherAdminExists(User $user): bool
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->whereNull('anonymized_at')
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /** Redirect to settings when authenticated, login otherwise. */
    private function tokenError(string $message): RedirectResponse
    {
        $user = Auth::user();

        if ($user !== null) {
            return redirect()
                ->route('users.edit', ['username' => $user->username])
                ->with('error', $message);
        }

        return redirect()->route('login')->with('error', $message);
    }

    /**
     * Resolve the target user by username and enforce the owner/admin gate.
     */
    private function resolveManageable(string $username): User
    {
        $user = User::query()->where('username', $username)->whereNull('anonymized_at')->firstOrFail();

        abort_unless(Auth::user()?->can('update', $user) ?? false, 403);

        return $user;
    }
}
