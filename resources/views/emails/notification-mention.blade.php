<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;">Olá, {{ $mentionedUserName }},</p>

    <p style="margin:0 0 16px;">Você foi mencionado no <strong>{{ config('orbita.name', 'Órbita') }}</strong>:</p>

    <p style="margin:0 0 16px;padding:12px 16px;background-color:#f4f4f5;border-left:3px solid #4f46e5;border-radius:4px;">
        {!! nl2br(e(\Illuminate\Support\Str::limit($content, 250))) !!}
    </p>

    <p style="margin:0 0 16px;">Para ver o conteúdo completo, acesse:<br>
        <a href="{{ $link }}" style="color:#4f46e5;">{{ $link }}</a>
    </p>
</body>
</html>
