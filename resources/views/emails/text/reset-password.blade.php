Olá{{ !empty($displayName) ? ', '.$displayName : '' }},

Você solicitou a redefinição de senha da sua conta no {{ config('orbita.name', 'Órbita') }}.

Para criar uma nova senha, acesse:
{{ $resetUrl }}

Este link é válido por 1 hora.
@if(!empty($email))

Email da conta: {{ $email }}
@endif

Se você não solicitou a redefinição de senha, ignore este email. Sua senha permanecerá a mesma e ninguém terá acesso à sua conta.
