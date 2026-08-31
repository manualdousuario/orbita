<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Web endpoints for comment edit, revisions, and delete.
 */
class CommentController extends Controller
{
    /**
     * GET /c/{hashid}/edit — standalone edit page (mounts comment-form in edit mode).
     */
    public function edit(string $hashid): View
    {
        $comment = Comment::query()
            ->with('post')
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->firstOrFail();

        abort_unless($this->canEdit($comment), 403);

        return view('comments.edit', ['comment' => $comment]);
    }

    /**
     * GET /c/{hashid}/revisions — current version + revision snapshots.
     */
    public function revisions(string $hashid): View
    {
        $comment = Comment::query()
            ->with('user', 'post')
            ->where('hashid', $hashid)
            ->whereNull('version_of')
            ->firstOrFail();

        abort_unless($this->canView($comment), 403);

        $versions = Comment::query()
            ->where('version_of', $comment->id)
            ->orderBy('id')
            ->get();

        return view('comments.revisions', [
            'comment' => $comment,
            'versions' => $versions,
        ]);
    }

    /**
     * DELETE /c/{hashid} — soft-delete a comment (owner or moderator/admin).
     */
    public function destroy(string $hashid): RedirectResponse
    {
        $comment = Comment::query()->where('hashid', $hashid)->whereNull('version_of')->firstOrFail();

        abort_unless($this->canDelete($comment), 403);

        $post = $comment->post;
        $comment->delete();

        if ($post !== null) {
            return redirect()->route('posts.show', ['hashid' => $post->hashid, 'slug' => $post->slug]);
        }

        return redirect()->route('home');
    }

    // Authorization delegates to CommentPolicy; thin wrappers centralize the gate.
    private function canEdit(Comment $comment): bool
    {
        return Auth::user()?->can('update', $comment) ?? false;
    }

    private function canDelete(Comment $comment): bool
    {
        return Auth::user()?->can('delete', $comment) ?? false;
    }

    private function canView(Comment $comment): bool
    {
        return Auth::user()?->can('view', $comment) ?? false;
    }
}
