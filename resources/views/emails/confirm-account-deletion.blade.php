<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;">Olá{{ !empty($displayName) ? ', '.$displayName : '' }},</p>

    <p style="margin:0 0 16px;">Você solicitou a exclusão da sua conta no <strong>{{ config('orbita.name', 'Órbita') }}</strong>.</p>

    <p style="margin:0 0 16px;">Para confirmar, clique no link abaixo. Ele abre uma página onde você ainda precisará clicar no botão de confirmação, nada é excluído só por abrir o link:</p>

    <p style="margin:0 0 16px;"><a href="{{ $confirmationUrl }}" style="color:#4f46e5;">{{ $confirmationUrl }}</a></p>

    <p style="margin:0 0 16px;"><strong>O que acontece ao confirmar:</strong> seus posts e comentários continuarão publicados, mas passarão a aparecer como <strong>&ldquo;Conta excluída&rdquo;</strong>, sem link para o seu perfil. Seu nome, email, avatar, bio, favoritos e notificações serão apagados, e seu email e nome de usuário ficarão livres para um novo cadastro.</p>

    <p style="margin:0 0 16px;"><strong>Esta ação é irreversível.</strong></p>

    <p style="margin:0 0 16px;">Este link expirará em 24 horas. Se você não solicitou esta exclusão, ignore este email. Sua conta permanecerá intacta. Por segurança, recomendamos também trocar a sua senha.</p>
</body>
</html>
