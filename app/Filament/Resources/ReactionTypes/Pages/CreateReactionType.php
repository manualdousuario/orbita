<?php

namespace App\Filament\Resources\ReactionTypes\Pages;

use App\Filament\Resources\ReactionTypes\ReactionTypeResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Create page for reaction types.
 */
class CreateReactionType extends CreateRecord
{
    protected static string $resource = ReactionTypeResource::class;
}
