<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Create page for static pages.
 */
class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
