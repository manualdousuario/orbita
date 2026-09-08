<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Support\ModerationLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament resource for moderating posts.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Posts';

    protected static ?string $modelLabel = 'post';

    protected static ?string $pluralModelLabel = 'posts';

    protected static ?int $navigationSort = 2;

    /** @var array<string, string> */
    protected static array $statuses = [
        'draft' => 'Rascunho',
        'published' => 'Publicado',
        'hidden' => 'Oculto',
        'revision' => 'Revisão',
        'closed' => 'Fechado',
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
                TextInput::make('title')->label('Título')->required()->maxLength(300),
                Textarea::make('content')->label('Conteúdo')->rows(12)->required()->columnSpanFull(),
                Select::make('status')->label('Status')->options(self::selectableStatuses())->required(),
                Toggle::make('allow_comments')->label('Permitir comentários'),
                TextInput::make('score')->label('Pontuação')->numeric()->required(),
            ]);
    }

    /**
     * Moderation toggles shared between the list rows and the edit page header.
     *
     * @return array<int, Action>
     */
    public static function moderationActions(): array
    {
        return [
            Action::make('hide')
                ->label('Ocultar')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->visible(fn (Post $record): bool => $record->status !== 'hidden')
                ->action(function (Post $record, $livewire): void {
                    $record->update(['status' => 'hidden']);
                    ModerationLogger::log('hide', 'post', $record->id, 'Post ocultado pelo administrador');
                    self::syncEditForm($livewire);
                })
                ->requiresConfirmation(),
            Action::make('unhide')
                ->label('Reexibir')
                ->icon(Heroicon::OutlinedEye)
                ->color('success')
                ->visible(fn (Post $record): bool => $record->status === 'hidden')
                ->action(function (Post $record, $livewire): void {
                    $record->update(['status' => 'published']);
                    ModerationLogger::log('unhide', 'post', $record->id, 'Post reexibido pelo administrador');
                    self::syncEditForm($livewire);
                })
                ->requiresConfirmation(),
            Action::make('pin')
                ->label('Fixar')
                ->icon(Heroicon::OutlinedMapPin)
                ->visible(fn (Post $record): bool => ! $record->is_pinned)
                ->action(function (Post $record): void {
                    $record->update(['is_pinned' => true]);
                    ModerationLogger::log('pin', 'post', $record->id, 'Post fixado pelo administrador');
                }),
            Action::make('unpin')
                ->label('Desafixar')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('gray')
                ->visible(fn (Post $record): bool => (bool) $record->is_pinned)
                ->action(function (Post $record): void {
                    $record->update(['is_pinned' => false]);
                    ModerationLogger::log('unpin', 'post', $record->id, 'Post desafixado pelo administrador');
                }),
            Action::make('lock_comments')
                ->label('Trancar comentários')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('warning')
                ->visible(fn (Post $record): bool => (bool) $record->allow_comments)
                ->action(function (Post $record, $livewire): void {
                    $record->update(['allow_comments' => false]);
                    ModerationLogger::log('lock_comments', 'post', $record->id, 'Comentários trancados pelo administrador');
                    self::syncEditForm($livewire);
                })
                ->requiresConfirmation(),
            Action::make('unlock_comments')
                ->label('Destrancar comentários')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('success')
                ->visible(fn (Post $record): bool => ! $record->allow_comments)
                ->action(function (Post $record, $livewire): void {
                    $record->update(['allow_comments' => true]);
                    ModerationLogger::log('unlock_comments', 'post', $record->id, 'Comentários destrancados pelo administrador');
                    self::syncEditForm($livewire);
                })
                ->requiresConfirmation(),
        ];
    }

    /**
     * Re-reads form fields an action changed after the form was filled.
     */
    private static function syncEditForm(mixed $livewire): void
    {
        if ($livewire instanceof EditRecord) {
            $livewire->refreshFormData(['status', 'allow_comments']);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->limit(60)->sortable(),
                TextColumn::make('user.username')->label('Autor')->searchable()->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'danger',
                        'revision' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_pinned')->label('Fixado')->boolean(),
                TextColumn::make('score')->label('Pontuação')->numeric()->sortable(),
                TextColumn::make('comment_count')->label('Comentários')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
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
                ...self::moderationActions(),
                Action::make('revisions')
                    ->label('Revisões')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (Post $record): bool => (int) $record->versions_count > 0)
                    ->url(fn (Post $record): string => route('posts.revisions', ['hashid' => $record->hashid]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make()
                    ->after(fn (Post $record) => ModerationLogger::log('remove', 'post', $record->id, 'Post excluído pelo administrador')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'edit' => EditPost::route('/{record}/edit'),
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
