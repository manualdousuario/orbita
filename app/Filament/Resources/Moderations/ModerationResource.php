<?php

namespace App\Filament\Resources\Moderations;

use App\Filament\Resources\Moderations\Pages\ListModerations;
use App\Models\Moderation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament resource for the moderation log.
 */
class ModerationResource extends Resource
{
    protected static ?string $model = Moderation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Moderação';

    protected static ?string $modelLabel = 'registro de moderação';

    protected static ?string $pluralModelLabel = 'moderação';

    protected static ?int $navigationSort = 9;

    /** @var array<string, string> */
    protected static array $actions = [
        'hide' => 'Ocultar',
        'unhide' => 'Reexibir',
        'remove' => 'Remover',
        'ban_user' => 'Banir usuário',
        'unban_user' => 'Desbanir usuário',
        'pin' => 'Fixar',
        'unpin' => 'Desafixar',
        'restore' => 'Restaurar',
        'lock_comments' => 'Trancar comentários',
        'unlock_comments' => 'Destrancar comentários',
        'auto_hide' => 'Ocultado automaticamente',
        'referral_stripped' => 'Link de referência removido',
        'activate_user' => 'Conta ativada',
    ];

    /** @var array<string, string> */
    protected static array $targetTypes = [
        'post' => 'Post',
        'comment' => 'Comentário',
        'user' => 'Usuário',
        'media' => 'Mídia',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('moderator.username')->label('Moderador')->placeholder('Sistema')->searchable()->sortable(),
                TextColumn::make('action')
                    ->label('Ação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$actions[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'hide', 'remove', 'ban_user' => 'danger',
                        'unhide', 'unban_user', 'restore', 'activate_user' => 'success',
                        'pin', 'unpin', 'auto_hide', 'referral_stripped' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('target_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => self::$targetTypes[$state] ?? $state),
                TextColumn::make('target_id')->label('Alvo')->numeric(),
                TextColumn::make('reason')->label('Motivo')->limit(60)->wrap(),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')->label('Ação')->options(self::$actions),
                SelectFilter::make('target_type')->label('Tipo')->options(self::$targetTypes),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModerations::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isStaff() ?? false;
    }
}
