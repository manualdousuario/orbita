Olá{{ !empty($displayName) ? ', '.$displayName : '' }},

Você solicitou a alteração de email da sua conta no {{ config('orbita.name', 'Órbita') }}.

Novo email: {{ $newEmail }}

Para confirmar esta alteração, acesse:
{{ $verificationUrl }}

Este link expirará em 24 horas. Se você não solicitou esta alteração, ignore este email. Seu email atual permanecerá inalterado.
