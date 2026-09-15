<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Services\ImageService;
use App\Support\ImageUrl;
use App\Support\ModerationLogger;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Filament resource for moderating media uploads.
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Mídias';

    protected static ?string $modelLabel = 'mídia';

    protected static ?string $pluralModelLabel = 'mídias';

    protected static ?int $navigationSort = 4;

    /** @var array<string, string> */
    protected static array $statuses = [
        'active' => 'Ativa',
        'processing' => 'Processando',
        'failed' => 'Falhou',
        'deleted' => 'Excluída',
    ];

    /** @var array<string, string> */
    protected static array $fileTypes = [
        'image' => 'Imagem',
        'video' => 'Vídeo',
        'audio' => 'Áudio',
        'document' => 'Documento',
        'other' => 'Outro',
    ];

    /** @var array<string, string> */
    protected static array $mediaTypes = [
        'post' => 'Post',
        'avatar' => 'Avatar',
        'ogimage' => 'OG Image',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('preview')
                    ->label('Prévia')
                    ->height(300)
                    ->getStateUsing(fn (Media $record): ?string => $record->file_type === 'image' ? ImageUrl::original($record->path) : null)
                    ->hidden(fn (?Media $record): bool => ! $record || $record->file_type !== 'image'),
                TextInput::make('file_name')->label('Nome do arquivo')->required()->maxLength(255),
                Select::make('status')->label('Status')->options(self::$statuses)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('Prévia')
                    ->height(50)
                    ->getStateUsing(fn (Media $record): ?string => $record->file_type === 'image' ? ImageUrl::original($record->path) : null),
                TextColumn::make('file_name')->label('Arquivo')->searchable()->limit(40)->sortable(),
                TextColumn::make('file_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$fileTypes[$state] ?? $state),
                TextColumn::make('media_type')
                    ->label('Uso')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$mediaTypes[$state] ?? $state),
                TextColumn::make('file_size')
                    ->label('Tamanho')
                    ->formatStateUsing(fn ($state): string => self::formatBytes((int) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'deleted', 'failed' => 'danger',
                        'processing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('uploader.username')->label('Enviado por')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Enviado em')->isoDateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::$statuses),
                SelectFilter::make('media_type')->label('Uso')->options(self::$mediaTypes),
                SelectFilter::make('file_type')->label('Tipo de arquivo')->options(self::$fileTypes),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->action(function (Media $record, ImageService $images): void {
                        $images->deleteImage((string) $record->path, (int) $record->id, (int) Auth::id());
                        ModerationLogger::log('remove', 'media', $record->id, 'Mídia excluída pelo administrador');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isStaff() ?? false;
    }
}
