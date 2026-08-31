<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;">Olá{{ !empty($displayName) ? ', '.$displayName : '' }},</p>

    <p style="margin:0 0 16px;">Você solicitou a redefinição de senha da sua conta no <strong>{{ config('orbita.name', 'Órbita') }}</strong>.</p>

    <p style="margin:0 0 16px;">Para criar uma nova senha, acesse:<br>
        <a href="{{ $resetUrl }}" style="color:#4f46e5;">{{ $resetUrl }}</a>
    </p>

    <p style="margin:0 0 16px;">Este link é válido por <strong>1 hora</strong>.</p>

    @if(!empty($email))
        <p style="margin:0 0 16px;"><strong>Email da conta:</strong> {{ $email }}</p>
    @endif

    <p style="margin:0 0 16px;">Se você não solicitou a redefinição de senha, ignore este email. Sua senha permanecerá a mesma e ninguém terá acesso à sua conta.</p>
</body>
</html>
