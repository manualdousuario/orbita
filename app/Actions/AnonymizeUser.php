<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserToken;
use App\Services\ImageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Anonymizes a user account by wiping all personal data.
 */
final class AnonymizeUser
{
    public const DISPLAY_NAME = 'Conta excluída';

    private const PLACEHOLDER_DOMAIN = 'orbita.invalid';

    public function __construct(private readonly ImageService $images) {}

    /**
     * Wipes the user's personal data and returns the anonymized user.
     */
    public function handle(User $user, ?int $actorId = null, ?UserToken $consumedToken = null): User
    {
        if ($user->anonymized_at !== null) {
            return $user;
        }

        $id = (int) $user->getKey();
        $previousEmail = (string) $user->email;
        $previousAvatarUrl = $user->avatar_url;
        $placeholderEmail = 'anonymized-'.$id.'@'.self::PLACEHOLDER_DOMAIN;

        DB::transaction(function () use ($user, $id, $previousEmail, $placeholderEmail, $consumedToken): void {
            if ($consumedToken !== null && ! $consumedToken->consume()) {
                return;
            }

            $now = Carbon::now();

            $user->forceFill([
                'display_name' => self::DISPLAY_NAME,
                'name' => null,
                'username' => 'anonymized-'.$id,
                'username_changed_at' => $now,
                'email' => $placeholderEmail,
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'bio' => null,
                'website' => null,
                'avatar_url' => null,
                'role' => UserRole::User,
                'notify_replies_email' => false,
                'notify_replies_system' => false,
                'notify_mentions_email' => false,
                'notify_mentions_system' => false,
                'notify_follows_email' => false,
                'notify_follows_system' => false,
                'default_comment_sort' => null,
                'last_login_at' => null,
                'last_login_ip' => null,
                'anonymized_at' => $now,
            ])->save();

            DB::table('password_reset_tokens')->where('email', $previousEmail)->delete();

            $user->bookmarks()->delete();
            $user->notifications()->delete();

            $user->socialAccounts()->delete();

            UserToken::query()
                ->where('user_id', $id)
                ->when($consumedToken !== null, fn ($query) => $query->whereKeyNot($consumedToken->getKey()))
                ->delete();

            $consumedToken?->forceFill([
                'email' => $placeholderEmail,
                'request_ip' => '0.0.0.0',
            ])->save();
        });

        if (filled($previousAvatarUrl)) {
            $this->images->deleteAvatarFile($previousAvatarUrl, $actorId ?? $id);
        }

        return $user;
    }
}
