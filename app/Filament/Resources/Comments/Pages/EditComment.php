<?php

namespace App\Filament\Resources\Comments\Pages;

use App\Filament\Resources\Comments\CommentResource;
use App\Models\Comment;
use App\Services\CommentService;
use App\Support\ModerationLogger;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Edit page for a comment.
 */
class EditComment extends EditRecord
{
    protected static string $resource = CommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn (Comment $record) => ModerationLogger::log('remove', 'comment', $record->id, 'Comentário removido pelo administrador')),
        ];
    }

    /**
     * Save via CommentService so the revision snapshot and edited_at stamp are kept.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Comment $record */
        // Allowlist, not the raw form array: see EditPost::handleRecordUpdate().
        $data = array_intersect_key($data, array_flip(['content', 'status', 'score']));

        return app(CommentService::class)->updateComment($record, $data);
    }
}
