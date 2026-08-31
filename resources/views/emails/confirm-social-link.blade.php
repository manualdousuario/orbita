<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;">Olá{{ !empty($displayName) ? ', '.$displayName : '' }},</p>

    <p style="margin:0 0 16px;">Alguém entrou com uma conta do <strong>{{ $providerLabel }}</strong> que usa o mesmo email da sua conta no <strong>{{ config('orbita.name', 'Órbita') }}</strong>.</p>

    <p style="margin:0 0 16px;">Se foi você, clique no link abaixo para conectar as duas contas. Depois disso você poderá entrar pelo {{ $providerLabel }} sem digitar sua senha:</p>

    <p style="margin:0 0 16px;"><a href="{{ $confirmUrl }}" style="color:#4f46e5;">{{ $confirmUrl }}</a></p>

    <p style="margin:0 0 16px;">Este link expirará em 24 horas. <strong>Se não foi você, ignore este email.</strong> Nada foi conectado à sua conta e ninguém entrou nela.</p>
</body>
</html>
