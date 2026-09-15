<?php

namespace App\Filament\Pages;

use App\Models\FailedJob;
use App\Support\QueueMetrics;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin page for inspecting and retrying failed queue jobs.
 */
class Queue extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Filas';

    protected static ?string $title = 'Filas';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isAdmin() ?? false;
    }

    public function pendingCount(): int
    {
        return QueueMetrics::pending();
    }

    public function failedCount(): int
    {
        return (int) DB::table('failed_jobs')->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('jobName')
                    ->label('Job')
                    ->description(fn (FailedJob $record): string => $record->uuid)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('payload', 'like', "%{$search}%")
                        ->orWhere('uuid', 'like', "%{$search}%")),
                TextColumn::make('queue')
                    ->label('Fila')
                    ->description(fn (FailedJob $record): string => (string) $record->connection)
                    ->searchable(),
                TextColumn::make('exceptionClass')
                    ->label('Exceção')
                    ->badge()
                    ->color('danger')
                    ->description(fn (FailedJob $record): string => Str::limit($record->exceptionMessage, 160))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('exception', 'like', "%{$search}%"))
                    ->action(fn (FailedJob $record): mixed => $this->mountTableAction('viewException', $record->getKey())),
                TextColumn::make('failed_at')->label('Falhou em')->isoDateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('viewException')
                    ->label('Detalhes')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->color('gray')
                    ->modalHeading('Detalhes da falha')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn (FailedJob $record): View => view(
                        'filament.pages.queue-exception',
                        ['record' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Action::make('retry')
                    ->label('Reenviar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        Notification::make()
                            ->title('Job reenviado para a fila.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('flush')
                    ->label('Limpar falhas')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        Artisan::call('queue:flush');
                        Notification::make()
                            ->title('Fila de falhas limpa.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhum job com falha')
            ->emptyStateDescription('Não há jobs com falha na fila.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryAll')
                ->label('Reenviar todos')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (): bool => $this->failedCount() > 0)
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);
                    Notification::make()
                        ->title('Todos os jobs com falha foram reenviados.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
