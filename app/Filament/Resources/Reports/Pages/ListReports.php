<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reports listing with read/unread tabs.
 */
class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'unread' => Tab::make('Não lidas')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('read_at'))
                ->badge(Report::query()->whereNull('read_at')->count())
                ->badgeColor('warning'),
            'read' => Tab::make('Lidas')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('read_at')),
            'all' => Tab::make('Todas'),
        ];
    }
}
