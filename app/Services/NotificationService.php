<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SocialProvider;
use App\Enums\UserRole;
use App\Mail\AccountDeletionConfirmationMail;
use App\Mail\CommentReplyMail;
use App\Mail\EmailChangeVerificationMail;
use App\Mail\MentionMail;
use App\Mail\NewCommentMail;
use App\Mail\NewReportMail;
use App\Mail\ResetPasswordMail;
use App\Mail\SocialLinkConfirmationMail;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Dispatches notification emails and system notifications for site activity.
 */
class NotificationService
{
    public function notifyNewCommentOnPost(Post $post, Comment $comment, User $commenter): void
    {
        if ((int) $post->user_id === (int) $comment->user_id) {
            return;
        }

        $postAuthor = User::query()->find($post->user_id);
        if ($postAuthor === null) {
            return;
        }

        $commentLink = app(CommentService::class)->permalinkFor($comment, $post, $postAuthor->default_comment_sort);

        if ($this->shouldNotify($postAuthor, 'replies', 'email', $post)) {
            Mail::to($postAuthor->email)->queue(new NewCommentMail(
                postTitle: (string) $post->title,
                authorName: (string) ($postAuthor->display_name ?? $postAuthor->username),
                commenterName: (string) ($commenter->display_name ?? $commenter->username),
                commentContent: (string) $comment->content,
                commentLink: $commentLink,
            ));
        }

        if ($this->shouldNotify($postAuthor, 'replies', 'system', $post)) {
            $this->createSystemNotification([
                'user_id' => (int) $postAuthor->id,
                'type' => 'comment',
                'title' => 'Novo comentário no seu post',
                'message' => sprintf(
                    '%s comentou no seu post "%s"',
                    $commenter->display_name ?? $commenter->username,
                    $post->title,
                ),
                'link' => $commentLink,
            ]);
        }
    }

    public function notifyCommentReply(Comment $parentComment, Comment $reply, User $replier): void
    {
        if ((int) $parentComment->user_id === (int) $reply->user_id) {
            return;
        }

        $commentAuthor = User::query()->find($parentComment->user_id);
        if ($commentAuthor === null) {
            return;
        }

        $post = Post::query()->find($parentComment->post_id);

        $replyLink = $post === null
            ? $this->postUrl($post).'#comment-'.$reply->hashid
            : app(CommentService::class)->permalinkFor($reply, $post, $commentAuthor->default_comment_sort);

        if ($this->shouldNotify($commentAuthor, 'replies', 'system', $post)) {
            $this->createSystemNotification([
                'user_id' => (int) $commentAuthor->id,
                'type' => 'reply',
                'title' => 'Nova resposta ao seu comentário',
                'message' => sprintf(
                    '%s respondeu ao seu comentário em "%s"',
                    $replier->display_name ?? $replier->username,
                    $post?->title,
                ),
                'link' => $replyLink,
            ]);
        }

        if ($this->shouldNotify($commentAuthor, 'replies', 'email', $post)) {
            Mail::to($commentAuthor->email)->queue(new CommentReplyMail(
                postTitle: (string) ($post?->title ?? ''),
                authorName: (string) ($commentAuthor->display_name ?? $commentAuthor->username),
                replierName: (string) ($replier->display_name ?? $replier->username),
                replyContent: (string) $reply->content,
                parentContent: (string) $parentComment->content,
                replyLink: $replyLink,
            ));
        }
    }

    /**
     * Notifies everyone following (bookmarking) the post about a new comment.
     *
     * @param  ?Comment  $parent  the comment being replied to, when this comment is a reply
     */
    public function notifyPostFollowers(Post $post, Comment $comment, User $commenter, ?Comment $parent = null): void
    {
        // Whoever is already covered by notifyNewCommentOnPost / notifyCommentReply.
        $skip = array_filter([
            (int) $comment->user_id,
            $parent === null ? (int) $post->user_id : (int) $parent->user_id,
        ]);

        $comments = app(CommentService::class);

        User::query()
            ->join('bookmarks', 'bookmarks.user_id', '=', 'users.id')
            ->where('bookmarks.post_id', (int) $post->id)
            ->whereNotIn('users.id', $skip)
            ->where('users.is_banned', false)
            ->whereNull('users.anonymized_at')
            ->select('users.*')
            ->chunkById(200, function ($followers) use ($post, $comment, $commenter, $comments): void {
                foreach ($followers as $follower) {
                    $link = $comments->permalinkFor($comment, $post, $follower->default_comment_sort);
                    $commenterName = (string) ($commenter->display_name ?? $commenter->username);

                    if ($this->shouldNotify($follower, 'follows', 'system')) {
                        $this->createSystemNotification([
                            'user_id' => (int) $follower->id,
                            'type' => 'comment',
                            'title' => 'Novo comentário em post que você acompanha',
                            'message' => sprintf('%s comentou em "%s"', $commenterName, $post->title),
                            'link' => $link,
                        ]);
                    }

                    if ($this->shouldNotify($follower, 'follows', 'email')) {
                        Mail::to($follower->email)->queue(new NewCommentMail(
                            postTitle: (string) $post->title,
                            authorName: (string) ($follower->display_name ?? $follower->username),
                            commenterName: $commenterName,
                            commentContent: (string) $comment->content,
                            commentLink: $link,
                            following: true,
                        ));
                    }
                }
            }, 'users.id', 'id');
    }

    public function notifyMention(User $user, string $content, string $link): bool
    {
        if ($this->shouldNotify($user, 'mentions', 'system')) {
            $this->createSystemNotification([
                'user_id' => (int) $user->id,
                'type' => 'mention',
                'title' => 'Você foi mencionado',
                'message' => $content,
                'link' => $link,
            ]);
        }

        if ($this->shouldNotify($user, 'mentions', 'email')) {
            Mail::to($user->email)->queue(new MentionMail(
                mentionedUserName: (string) ($user->display_name ?? $user->username),
                content: $content,
                link: $link,
            ));
        }

        return true;
    }

    public function sendEmailChangeVerification(User $user, string $newEmail, string $token): bool
    {
        // The Mailable builds the confirmation URL from the named route.
        Mail::to($newEmail)->queue(new EmailChangeVerificationMail(
            displayName: (string) ($user->display_name ?? $user->username ?? ''),
            newEmail: $newEmail,
            token: $token,
        ));

        return true;
    }

    public function sendSocialLinkConfirmation(User $user, SocialProvider $provider, string $token): bool
    {
        Mail::to((string) $user->email)->queue(new SocialLinkConfirmationMail(
            displayName: (string) ($user->display_name ?? $user->username ?? ''),
            providerLabel: $provider->label(),
            token: $token,
        ));

        return true;
    }

    public function sendAccountDeletionConfirmation(User $user, string $token): bool
    {
        Mail::to($user->email)->queue(new AccountDeletionConfirmationMail(
            displayName: (string) ($user->display_name ?? $user->username ?? ''),
            token: $token,
        ));

        return true;
    }

    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        $url = rtrim((string) config('orbita.url'), '/').'/reset-password/'.$token;

        Mail::to($user->email)->queue(new ResetPasswordMail(
            displayName: (string) ($user->display_name ?? $user->username ?? ''),
            email: (string) $user->email,
            resetUrl: $url,
        ));

        return true;
    }

    public function notifyStaffOfReport(Report $report, Post|Comment $target, int $reportCount, bool $autoHidden = false): void
    {
        try {
            $isPost = $target instanceof Post;
            $post = $isPost ? $target : $target->post;

            $contentUrl = $isPost || $post === null
                ? $this->postUrl($post)
                : app(CommentService::class)->permalinkFor($target, $post);

            $adminUrl = rtrim((string) config('orbita.url'), '/')
                .'/admin/'.($isPost ? 'posts' : 'comments').'/'.$target->id.'/edit';

            $content = $isPost
                ? trim((string) $target->title."\n\n".(string) $target->content)
                : (string) $target->content;

            foreach ($this->recipients([UserRole::Moderator, UserRole::Admin]) as $staff) {
                Mail::to($staff->email)->queue(new NewReportMail(
                    targetLabel: $isPost ? 'Post' : 'Comentário',
                    postTitle: (string) ($post?->title ?? '(post removido)'),
                    targetContent: $content,
                    targetAuthor: (string) ($target->user?->display_name ?? $target->user?->username ?? '(desconhecido)'),
                    reporterName: (string) ($report->reporter?->display_name ?? $report->reporter?->username ?? '(desconhecido)'),
                    reason: $report->reason,
                    contentUrl: $contentUrl,
                    adminUrl: $adminUrl,
                    reportCount: $reportCount,
                    autoHidden: $autoHidden,
                ));
            }
        } catch (Throwable $e) {
            Log::error('Falha ao notificar a equipe sobre uma denúncia', [
                'report_id' => $report->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function recipients(array $roles)
    {
        return User::query()
            ->whereIn('role', $roles)
            ->where('is_banned', false)
            ->whereNull('anonymized_at')
            ->whereNotNull('email')
            ->get(['id', 'email']);
    }

    /**
     * Persists a system notification row and returns its id.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSystemNotification(array $data): int
    {
        $notification = Notification::create([
            'user_id' => (int) $data['user_id'],
            'type' => (string) ($data['type'] ?? 'system'),
            'title' => (string) ($data['title'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'link' => $data['link'] ?? null,
            'is_read' => false,
        ]);

        Log::info('System notification created', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
        ]);

        return (int) $notification->id;
    }

    private function shouldNotify(User $user, string $type, string $channel, mixed $context = null): bool
    {
        if ($user->isAnonymized()) {
            return false;
        }

        $field = "notify_{$type}_{$channel}";

        if ($context !== null) {
            $contextValue = data_get($context, $field);
            if ($contextValue !== null) {
                return (bool) $contextValue;
            }
        }

        if ($user->{$field} !== null) {
            return (bool) $user->{$field};
        }

        return false;
    }

    private function postUrl(?Post $post): string
    {
        if ($post === null) {
            return rtrim((string) config('orbita.url'), '/');
        }

        return rtrim((string) config('orbita.url'), '/').'/p/'.$post->hashid;
    }
}
