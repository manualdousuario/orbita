<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Services\PostService;
use App\Support\ModerationLogger;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Edit page for a post.
 */
class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Header actions mirroring the list-row moderation toggles.
     */
    protected function getHeaderActions(): array
    {
        return [
            ...PostResource::moderationActions(),
            DeleteAction::make()
                ->after(fn (Post $record) => ModerationLogger::log('remove', 'post', $record->id, 'Post excluído pelo administrador')),
        ];
    }

    /**
     * Save via PostService so revisions, edited_at and the slug are kept.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Post $record */
        // Allowlist the form fields; Post::$fillable alone would admit version_of, user_id, score.
        $data = array_intersect_key($data, array_flip(['title', 'content', 'url', 'status', 'is_pinned', 'allow_comments', 'score']));

        return app(PostService::class)->updatePost($record, $data);
    }
}
