<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Services\ImageService;
use App\Support\Avatar;
use App\Support\ModerationLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Filament resource for user management.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static ?int $navigationSort = 1;

    private static function panelUser(): ?User
    {
        $user = Filament::auth()->user();

        return $user;
    }

    private static function panelUserIsAdmin(): bool
    {
        return self::panelUser()?->isAdmin() ?? false;
    }

    private static function panelUserIsStaff(): bool
    {
        return self::panelUser()?->isStaff() ?? false;
    }

    public static function canViewAny(): bool
    {
        return self::panelUserIsStaff();
    }

    private static function removeAvatar(User $record, ImageService $images): void
    {
        $images->deleteAvatarFile($record->avatar_url, (int) Auth::id());

        $record->update(['avatar_url' => null]);

        ModerationLogger::log('remove', 'user', $record->id, 'Avatar removido pelo administrador');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (self::panelUserIsStaff()) {
            return Response::allow();
        }

        return parent::getEditAuthorizationResponse($record);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! self::panelUserIsAdmin()) {
            return Response::deny();
        }

        if ($record->getKey() === self::panelUser()?->getKey()) {
            return Response::deny();
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function form(Schema $schema): Schema
    {
        $isAnonymized = fn (?User $record): bool => $record?->isAnonymized() ?? false;

        return $schema
            ->components([
                Select::make('role')
                    ->label('Função')
                    ->options(UserRole::options())
                    ->required()
                    ->disabled(fn (?User $record): bool => ! self::panelUserIsAdmin() || $isAnonymized($record)),
                Toggle::make('is_banned')
                    ->label('Banido')
                    ->disabled(fn (?User $record): bool => ! self::panelUserIsAdmin() || $isAnonymized($record)),
                Toggle::make('notify_replies_email')->label('Notificar respostas por e-mail')->disabled($isAnonymized),
                Toggle::make('notify_replies_system')->label('Notificar respostas no sistema')->disabled($isAnonymized),
                Toggle::make('notify_mentions_email')->label('Notificar menções por e-mail')->disabled($isAnonymized),
                Toggle::make('notify_mentions_system')->label('Notificar menções no sistema')->disabled($isAnonymized),
                Toggle::make('notify_follows_email')->label('Notificar posts acompanhados por e-mail')->disabled($isAnonymized),
                Toggle::make('notify_follows_system')->label('Notificar posts acompanhados no sistema')->disabled($isAnonymized),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->imageSize(30)
                    ->getStateUsing(fn (User $record): ?string => filled($record->avatar_url) ? url($record->avatar_url) : null)
                    ->defaultImageUrl(fn (User $record): string => Avatar::url((string) ($record->username ?? ''))),
                TextColumn::make('username')->label('Usuário')->searchable()->sortable(),
                TextColumn::make('display_name')->label('Nome de exibição')->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->visible(fn (): bool => Filament::auth()->user()?->isAdmin() ?? false),
                TextColumn::make('role')
                    ->label('Função')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Admin => 'danger',
                        UserRole::Moderator => 'warning',
                        UserRole::User => 'gray',
                    }),
                IconColumn::make('is_banned')->label('Banido')->boolean(),
                IconColumn::make('anonymized_at')->label('Excluída')->boolean(),
                TextColumn::make('posts_count')
                    ->label('Posts')
                    ->counts('posts')
                    ->numeric()
                    ->sortable()
                    ->url(fn (User $record): string => PostResource::getUrl('index', [
                        'tableFilters' => ['user' => ['value' => $record->id]],
                    ])),
                TextColumn::make('comments_count')
                    ->label('Comentários')
                    ->counts('comments')
                    ->numeric()
                    ->sortable()
                    ->url(fn (User $record): string => CommentResource::getUrl('index', [
                        'tableFilters' => ['user' => ['value' => $record->id]],
                    ])),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Função')
                    ->options(UserRole::options()),
                TernaryFilter::make('is_banned')->label('Banido'),
                TernaryFilter::make('anonymized_at')
                    ->label('Excluída')
                    ->nullable()
                    ->trueLabel('Somente excluídas')
                    ->falseLabel('Somente ativas'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('ban')
                    ->label('Banir')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (User $record): bool => self::panelUserIsAdmin() && ! $record->is_banned && ! $record->isAnonymized())
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->forceFill(['is_banned' => true])->save();
                        ModerationLogger::log('ban_user', 'user', $record->id, $data['reason']);
                    })
                    ->requiresConfirmation(),
                Action::make('unban')
                    ->label('Desbanir')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (User $record): bool => self::panelUserIsAdmin() && (bool) $record->is_banned && ! $record->isAnonymized())
                    ->action(function (User $record): void {
                        $record->forceFill(['is_banned' => false])->save();
                        ModerationLogger::log('unban_user', 'user', $record->id, 'Desbanido pelo administrador');
                    })
                    ->requiresConfirmation(),
                Action::make('removeAvatar')
                    ->label('Remover avatar')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('danger')
                    ->visible(fn (User $record): bool => self::panelUserIsStaff() && filled($record->avatar_url) && ! $record->isAnonymized())
                    ->modalDescription('O avatar atual será removido. O usuário volta a exibir o avatar padrão gerado automaticamente até enviar ou receber um novo.')
                    ->requiresConfirmation()
                    ->action(fn (User $record, ImageService $images) => self::removeAvatar($record, $images)),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
