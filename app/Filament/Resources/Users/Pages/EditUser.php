<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\AnonymizeUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\ModerationLogger;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * Edit page for a user.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected const PRIVILEGED_ATTRIBUTES = ['role', 'is_banned'];

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return 'Editar usuário: '.$record->username;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return $record->email;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('forceActivate')
                ->label('Ativar conta forçadamente')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (User $record): bool => (Filament::auth()->user()?->isAdmin() ?? false)
                    && is_null($record->email_verified_at))
                ->requiresConfirmation()
                ->modalDescription('A conta será marcada como verificada sem envio de e-mail de confirmação.')
                ->action(function (User $record): void {
                    $record->forceFill(['email_verified_at' => now()])->save();

                    ModerationLogger::log('activate_user', 'user', $record->id, 'Conta ativada forçadamente pelo administrador');

                    Notification::make()
                        ->title('Conta ativada com sucesso.')
                        ->success()
                        ->send();
                }),
            Action::make('sendPasswordReset')
                ->label('Enviar link de redefinição de senha')
                ->icon(Heroicon::OutlinedEnvelope)
                ->visible(fn (): bool => Filament::auth()->user()?->isAdmin() ?? false)
                ->requiresConfirmation()
                ->modalDescription(fn (User $record): string => "Um e-mail com o link de redefinição de senha será enviado para {$record->email}.")
                ->action(function (User $record): void {
                    $status = Password::sendResetLink(['email' => $record->email]);

                    if ($status === Password::RESET_LINK_SENT) {
                        Notification::make()
                            ->title("Link de redefinição enviado para {$record->email}.")
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Não foi possível enviar o link de redefinição.')
                        ->body(__($status))
                        ->danger()
                        ->send();
                }),
            Action::make('anonymize')
                ->label('Excluir conta (anonimizar)')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (User $record): bool => (Filament::auth()->user()?->isAdmin() ?? false)
                    && ! $record->isAnonymized()
                    && ! $record->is(Filament::auth()->user()))
                ->schema([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading(fn (User $record): string => "Excluir conta de {$record->username}")
                ->modalDescription('Os posts e comentários continuarão publicados, mas a autoria passará a exibir "Conta excluída". Nome, e-mail, avatar, bio, favoritos e notificações serão apagados, e o e-mail e o nome de usuário ficarão livres para um novo cadastro. Esta ação é irreversível.')
                ->modalSubmitActionLabel('Excluir conta')
                ->action(function (User $record, array $data, AnonymizeUser $anonymize): void {
                    abort_if($record->is(Filament::auth()->user()), 403);

                    $previousUsername = (string) $record->username;

                    $anonymize->handle($record, (int) Auth::id());
                    ModerationLogger::log('remove', 'user', $record->id, $data['reason'], [
                        'type' => 'account_anonymized',
                        'previous_username' => $previousUsername,
                    ]);

                    Notification::make()
                        ->title('Conta anonimizada. O conteúdo permanece publicado como "Conta excluída".')
                        ->success()
                        ->send();

                    $this->redirect(UserResource::getUrl('index'));
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $privileged = array_intersect_key($data, array_flip(self::PRIVILEGED_ATTRIBUTES));
        $data = array_diff_key($data, $privileged);

        if ($privileged !== []) {
            abort_unless(Filament::auth()->user()?->isAdmin() ?? false, 403);
            abort_if($record instanceof User && $record->isAnonymized(), 403);

            $record->forceFill($privileged);
        }

        $record->fill($data)->save();

        if ($record->wasChanged('is_banned')) {
            ModerationLogger::log(
                $record->getAttribute('is_banned') ? 'ban_user' : 'unban_user',
                'user',
                (int) $record->getKey(),
                'Alterado na edição do usuário',
            );
        }

        return $record;
    }
}
