<?php

namespace App\Filament\Resources\ReactionTypes\Pages;

use App\Filament\Resources\ReactionTypes\ReactionTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit page for a reaction type.
 */
class EditReactionType extends EditRecord
{
    protected static string $resource = ReactionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
