<?php

namespace App\Filament\Resources\ReactionTypes\Pages;

use App\Filament\Resources\ReactionTypes\ReactionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Reaction types listing page.
 */
class ListReactionTypes extends ListRecords
{
    protected static string $resource = ReactionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
