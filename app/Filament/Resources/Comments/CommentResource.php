<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\EditComment;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use App\Services\CommentService;
use App\Support\ModerationLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament resource for moderating comments.
 */
class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Comentários';

    protected static ?string $modelLabel = 'comentário';

    protected static ?string $pluralModelLabel = 'comentários';

    protected static ?int $navigationSort = 3;

    /** @var array<string, string> */
    protected static array $statuses = [
        'visible' => 'Visível',
        'hidden' => 'Oculto',
        'removed' => 'Removido',
        'revision' => 'Revisão',
    ];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('version_of')->withCount('versions');
    }

    private static function selectableStatuses(): array
    {
        return array_diff_key(self::$statuses, ['revision' => null]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')->label('Conteúdo')->rows(8)->required()->columnSpanFull(),
                Select::make('status')->label('Status')->options(self::selectableStatuses())->required(),
                TextInput::make('score')->label('Pontuação')->numeric()->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')->label('Comentário')->searchable()->wrap(),
                TextColumn::make('user.username')->label('Autor')->searchable()->sortable(),
                TextColumn::make('post.title')->label('Post')->limit(40)->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'visible' => 'success',
                        'hidden', 'removed' => 'danger',
                        'revision' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Criado em')->isoDateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::selectableStatuses()),
                SelectFilter::make('user')
                    ->label('Autor')
                    ->relationship('user', 'username')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('hide')
                    ->label('Ocultar')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('danger')
                    ->visible(fn (Comment $record): bool => $record->status !== 'hidden')
                    ->action(function (Comment $record): void {
                        $record->update(['status' => 'hidden']);
                        ModerationLogger::log('hide', 'comment', $record->id, 'Comentário ocultado pelo administrador');
                    })
                    ->requiresConfirmation(),
                Action::make('unhide')
                    ->label('Reexibir')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('success')
                    ->visible(fn (Comment $record): bool => $record->status === 'hidden')
                    ->action(function (Comment $record): void {
                        $record->update(['status' => 'visible']);
                        ModerationLogger::log('unhide', 'comment', $record->id, 'Comentário reexibido pelo administrador');
                    })
                    ->requiresConfirmation(),
                Action::make('view')
                    ->label('Ver no site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (Comment $record): bool => $record->post !== null)
                    ->url(fn (Comment $record): ?string => $record->post === null
                        ? null
                        : app(CommentService::class)->permalinkFor($record, $record->post))
                    ->openUrlInNewTab(),
                Action::make('revisions')
                    ->label('Revisões')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (Comment $record): bool => (int) $record->versions_count > 0)
                    ->url(fn (Comment $record): string => route('comments.revisions', ['hashid' => $record->hashid]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make()
                    ->after(fn (Comment $record) => ModerationLogger::log('remove', 'comment', $record->id, 'Comentário removido pelo administrador')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComments::route('/'),
            'edit' => EditComment::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isStaff() ?? false;
    }
}
