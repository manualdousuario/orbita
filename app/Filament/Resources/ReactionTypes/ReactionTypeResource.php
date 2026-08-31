<?php

namespace App\Filament\Resources\ReactionTypes;

use App\Filament\Resources\ReactionTypes\Pages\CreateReactionType;
use App\Filament\Resources\ReactionTypes\Pages\EditReactionType;
use App\Filament\Resources\ReactionTypes\Pages\ListReactionTypes;
use App\Models\ReactionType;
use App\Support\Slug;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament resource for reaction types.
 */
class ReactionTypeResource extends Resource
{
    protected static ?string $model = ReactionType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?string $navigationLabel = 'Reações';

    protected static ?string $modelLabel = 'reação';

    protected static ?string $pluralModelLabel = 'reações';

    protected static ?int $navigationSort = 7;

    /** @var array<string, string> */
    protected static array $roles = [
        'user' => 'Usuário',
        'moderator' => 'Moderador',
        'admin' => 'Administrador',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Slug::make($state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('emoji')->label('Emoji')->required()->maxLength(10),
                TextInput::make('score')->label('Pontuação')->numeric()->default(0)->required(),
                TextInput::make('display_order')->label('Ordem de exibição')->numeric()->default(0)->required(),
                Select::make('allowed_roles')
                    ->label('Funções permitidas')
                    ->multiple()
                    ->options(self::$roles)
                    ->default(['user', 'moderator', 'admin'])
                    ->required(),
                Toggle::make('is_active')->label('Ativo')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emoji')->label('Emoji'),
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('score')->label('Pontuação')->numeric()->sortable(),
                TextColumn::make('display_order')->label('Ordem')->numeric()->sortable(),
                IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Ativo'),
            ])
            ->defaultSort('display_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReactionTypes::route('/'),
            'create' => CreateReactionType::route('/create'),
            'edit' => EditReactionType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }
}
