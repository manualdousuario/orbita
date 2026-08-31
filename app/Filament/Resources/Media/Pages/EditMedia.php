<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Support\ModerationLogger;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit page for a media record.
 */
class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->action(function (Media $record): void {
                    $record->update(['status' => 'deleted', 'deleted_by' => auth()->id()]);
                    $record->delete();
                    ModerationLogger::log('remove', 'media', $record->id, 'Mídia excluída pelo administrador');
                }),
        ];
    }
}
