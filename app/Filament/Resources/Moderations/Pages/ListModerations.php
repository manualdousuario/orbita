<?php

namespace App\Filament\Resources\Moderations\Pages;

use App\Filament\Resources\Moderations\ModerationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Moderation log listing page.
 */
class ListModerations extends ListRecords
{
    protected static string $resource = ModerationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
