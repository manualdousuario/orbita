<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\BookmarkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Post bookmark endpoints.
 */
class BookmarkController extends Controller
{
    public function index(): View
    {
        return view('bookmarks.index');
    }

    /**
     * Toggle a bookmark on a post for the current user.
     */
    public function toggle(Request $request, BookmarkService $bookmarks, string $hashid): JsonResponse
    {
        $user = Auth::user();

        if ($user->isPendingActivation()) {
            return response()->json([
                'message' => 'Ative sua conta para acompanhar posts.',
                'url' => route('verification.notice'),
            ], 403);
        }

        $post = Post::query()
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->whereIn('status', ['published', 'closed'])
            ->firstOrFail(['id']);

        return response()->json([
            'bookmarked' => $bookmarks->toggle((int) $user->id, (int) $post->id),
        ]);
    }
}
