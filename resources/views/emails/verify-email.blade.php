<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;">Olá{{ !empty($displayName) ? ', '.$displayName : '' }},</p>

    <p style="margin:0 0 16px;">Obrigado por se registrar no <strong>{{ config('orbita.name', 'Órbita') }}</strong>! Para ativar sua conta, precisamos confirmar seu endereço de email.</p>

    <p style="margin:0 0 16px;">Clique no link abaixo para verificar seu email:</p>

    <p style="margin:0 0 16px;"><a href="{{ $verificationUrl }}" style="color:#4f46e5;">{{ $verificationUrl }}</a></p>

    <p style="margin:0 0 16px;">Depois de ativar, você recebe notificações de respostas, menções e novos comentários nos posts que acompanha, no site e por email. Para desligar qualquer uma delas, acesse <a href="{{ $preferencesUrl }}" style="color:#4f46e5;">as opções do seu perfil</a>.</p>

    <p style="margin:0 0 16px;">Este link é válido por 24 horas. Se você não solicitou este registro, pode ignorar este email.</p>
</body>
</html>
