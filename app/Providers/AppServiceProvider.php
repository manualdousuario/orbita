<?php

namespace App\Providers;

use App\Events\AccountDeletionRequested;
use App\Events\CommentCreated;
use App\Events\CommentReplied;
use App\Events\EmailChangeRequested;
use App\Events\ReportCreated;
use App\Events\SocialLinkConfirmationRequested;
use App\Events\UsersMentioned;
use App\Listeners\NotifyPostFollowers;
use App\Listeners\ProvisionAvatarForNewUser;
use App\Listeners\SendAccountDeletionConfirmation;
use App\Listeners\SendCommentNotification;
use App\Listeners\SendEmailChangeVerification;
use App\Listeners\SendMentionNotification;
use App\Listeners\SendReplyNotification;
use App\Listeners\SendSocialLinkConfirmation;
use App\Listeners\TriageNewReport;
use App\Listeners\VerifyDatabaseIsReachable;
use App\Listeners\VerifyValkeyIsReachable;
use App\Models\Comment;
use App\Models\Post;
use App\Observers\CommentObserver;
use App\Observers\PostObserver;
use App\Services\ReactionService;
use App\Support\FeedGeneration;
use App\Support\MentionResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use SocialiteProviders\Instagram\InstagramExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

/**
 * Application services: events, gates, pagination and observers.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ReactionService::class);
        $this->app->scoped(MentionResolver::class);
        $this->app->scoped(FeedGeneration::class);
    }

    public function boot(): void
    {
        $this->requireValkeyBackedDrivers();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Comment::observe(CommentObserver::class);
        Post::observe(PostObserver::class);

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        $this->registerEventListeners();
    }

    private function requireValkeyBackedDrivers(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $drivers = [
            'cache.default' => 'redis',
            'session.driver' => 'redis',
            'queue.default' => 'redis',
        ];

        foreach ($drivers as $key => $expected) {
            if (config($key) !== $expected) {
                throw new RuntimeException(
                    "Órbita requires Valkey: config('{$key}') is '".(string) config($key)."', expected '{$expected}'. "
                    .'Remove the overriding variable from .env — there is no file or database fallback.'
                );
            }
        }

        $markdownStore = (string) config('markdown.cache_store');

        if (config("cache.stores.{$markdownStore}.driver") !== 'redis') {
            throw new RuntimeException(
                "Órbita requires Valkey: the Markdown cache store '{$markdownStore}' is not Redis-backed. "
                .'Unset MARKDOWN_CACHE_STORE.'
            );
        }
    }

    private function registerEventListeners(): void
    {
        Event::listen(CommentCreated::class, SendCommentNotification::class);
        Event::listen(CommentReplied::class, SendReplyNotification::class);
        Event::listen(CommentCreated::class, NotifyPostFollowers::class);
        Event::listen(CommentReplied::class, NotifyPostFollowers::class);
        Event::listen(EmailChangeRequested::class, SendEmailChangeVerification::class);
        Event::listen(AccountDeletionRequested::class, SendAccountDeletionConfirmation::class);
        Event::listen(UsersMentioned::class, SendMentionNotification::class);
        Event::listen(ReportCreated::class, TriageNewReport::class);
        Event::listen(Registered::class, ProvisionAvatarForNewUser::class);
        Event::listen(SocialLinkConfirmationRequested::class, SendSocialLinkConfirmation::class);
        Event::listen(SocialiteWasCalled::class, [InstagramExtendSocialite::class, 'handle']);

        Event::listen(DiagnosingHealth::class, VerifyDatabaseIsReachable::class);
        Event::listen(DiagnosingHealth::class, VerifyValkeyIsReachable::class);
    }
}
