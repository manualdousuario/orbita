<?php

use App\Enums\SocialProvider;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\SocialLinkController;
use App\Http\Controllers\Auth\SocialRegistrationController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OgImageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialConnectionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Support\HomeFeedTabs;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Base
|--------------------------------------------------------------------------
*/
Route::view('/', 'home')->name('home');

foreach (HomeFeedTabs::all() as $tabKey => $tabMeta) {
    Route::view($tabMeta['path'], 'home', ['tab' => $tabKey])
        ->name(HomeFeedTabs::routeName($tabKey));
}

Route::redirect('/home', '/');

Route::view('/search', 'search')->name('search');

Route::get('/page/{slug}', [PageController::class, 'show'])->name('pages.show');

/*
|--------------------------------------------------------------------------
| RSS/Feed/Robots/Sitemap
|--------------------------------------------------------------------------
*/
Route::get('/feed', [FeedController::class, 'posts'])->name('feed');
Route::get('/feed/tag/{slug}', [FeedController::class, 'tag'])->name('feed.tag');

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-posts-{page}.xml', [SitemapController::class, 'posts'])
    ->whereNumber('page')
    ->name('sitemap.posts');

Route::get('/t/{slug}', [TagController::class, 'show'])->name('tags.show');

Route::get('/u/{username}', [UserController::class, 'profile'])->name('users.profile');

/*
|--------------------------------------------------------------------------
| Register
|--------------------------------------------------------------------------
*/
Route::get('/verify-email-change/{token}', [UserController::class, 'confirmEmailChange'])->name('users.email.confirm');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:verification-verify')
    ->name('verification.verify');

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
    ->name('verification.notice');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware('throttle:verification-send')
    ->name('verification.send');

Route::get('/confirm-account-deletion/{token}', [UserController::class, 'showAccountDeletionConfirmation'])->name('users.delete-account.confirm');
Route::post('/confirm-account-deletion/{token}', [UserController::class, 'confirmAccountDeletion'])->name('users.delete-account.execute');

/*
|--------------------------------------------------------------------------
| Social login (OAuth via Socialite)
|--------------------------------------------------------------------------
*/
Route::prefix('auth/{provider}')
    ->whereIn('provider', SocialProvider::values())
    ->group(function () {
        Route::get('/redirect', [SocialAuthController::class, 'redirect'])
            ->middleware('throttle:social-redirect')
            ->name('social.redirect');

        Route::get('/callback', [SocialAuthController::class, 'callback'])
            ->middleware('throttle:social-callback')
            ->name('social.callback');
    });

Route::get('/auth/complete-registration', [SocialRegistrationController::class, 'show'])->name('social.complete');
Route::post('/auth/complete-registration', [SocialRegistrationController::class, 'store'])
    ->middleware('throttle:social-complete')
    ->name('social.complete.store');

Route::get('/confirm-social-link/{token}', [SocialLinkController::class, 'confirm'])
    ->middleware('throttle:12,60')
    ->name('social.link.confirm');

/*
|--------------------------------------------------------------------------
| Posts & comments
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::middleware('verified')->group(function () {
        Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

        Route::get('/p/{hashid}/edit', [PostController::class, 'edit'])->name('posts.edit');
        Route::put('/p/{hashid}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('/p/{hashid}', [PostController::class, 'destroy'])->name('posts.destroy');

        Route::get('/c/{hashid}/edit', [CommentController::class, 'edit'])->name('comments.edit');
        Route::delete('/c/{hashid}', [CommentController::class, 'destroy'])->name('comments.destroy');
    });

    Route::get('/u/{username}/edit', [UserController::class, 'edit'])->name('users.edit');

    Route::put('/u/{username}', [UserController::class, 'update'])
        ->middleware('throttle:30,60')
        ->name('users.update');
    Route::put('/u/{username}/email', [UserController::class, 'updateEmail'])
        ->middleware('throttle:5,60')
        ->name('users.email');
    Route::put('/u/{username}/password', [UserController::class, 'updatePassword'])
        ->middleware('throttle:10,60')
        ->name('users.password');

    Route::post('/u/{username}/delete-account', [UserController::class, 'requestAccountDeletion'])
        ->middleware('throttle:5,60')
        ->name('users.delete-account');

    Route::post('/u/{username}/connections/{provider}', [SocialConnectionController::class, 'store'])
        ->whereIn('provider', SocialProvider::values())
        ->name('users.connections.store');
    Route::delete('/u/{username}/connections/{provider}', [SocialConnectionController::class, 'destroy'])
        ->whereIn('provider', SocialProvider::values())
        ->name('users.connections.destroy');

    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');

    Route::post('/p/{hashid}/bookmark', [BookmarkController::class, 'toggle'])
        ->middleware('throttle:120,1')
        ->name('bookmarks.toggle');

    Route::get('/mention-search', [UserController::class, 'mentionSearch'])
        ->middleware('throttle:60,1')
        ->name('users.mention-search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id')->name('notifications.read');
});

Route::get('/p/{hashid}/revisions', [PostController::class, 'revisions'])->name('posts.revisions');
Route::get('/c/{hashid}/revisions', [CommentController::class, 'revisions'])->name('comments.revisions');

/*
|--------------------------------------------------------------------------
| Storage
|--------------------------------------------------------------------------
*/
Route::get('/s/og/{hashid}.jpg', OgImageController::class)
    ->where('hashid', '[A-Za-z0-9]+')
    ->middleware(['throttle:30,1', 'cache.headers:public;max_age=31536000;immutable'])
    ->name('posts.og');

Route::get('/s/{path}', [ImageController::class, 'show'])
    ->where('path', '.+')
    ->middleware(['throttle:images', 'cache.headers:public;max_age=31536000;immutable'])
    ->name('image');

Route::get('/p/{hashid}/{slug?}', [PostController::class, 'show'])->name('posts.show');
