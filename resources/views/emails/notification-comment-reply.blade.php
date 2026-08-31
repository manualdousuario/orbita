<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;">Olá, {{ $authorName }},</p>

    <p style="margin:0 0 16px;"><strong>{{ $replierName }}</strong> respondeu ao seu comentário em: <strong>{{ $postTitle }}</strong></p>

    <p style="margin:0 0 4px;font-size:14px;color:#71717a;">Seu comentário:</p>
    <p style="margin:0 0 16px;padding:12px 16px;background-color:#f4f4f5;border-left:3px solid #a1a1aa;border-radius:4px;">
        {!! nl2br(e(\Illuminate\Support\Str::limit($parentContent, 150))) !!}
    </p>

    <p style="margin:0 0 4px;font-size:14px;color:#71717a;">Resposta:</p>
    <p style="margin:0 0 16px;padding:12px 16px;background-color:#f4f4f5;border-left:3px solid #4f46e5;border-radius:4px;">
        {!! nl2br(e(\Illuminate\Support\Str::limit($replyContent, 300))) !!}
    </p>

    <p style="margin:0 0 16px;">Para responder, acesse:<br>
        <a href="{{ $replyLink }}" style="color:#4f46e5;">{{ $replyLink }}</a>
    </p>
</body>
</html>
