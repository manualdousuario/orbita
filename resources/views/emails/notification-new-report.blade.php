<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#18181b;">
    <p style="margin:0 0 16px;font-size:17px;"><strong>🚨 Nova denúncia</strong></p>

    @if ($autoHidden)
        <p style="margin:0 0 16px;padding:12px 16px;background-color:#fef2f2;border-left:3px solid #dc2626;border-radius:4px;">
            Este conteúdo foi <strong>ocultado automaticamente</strong>.
            Para reexibir, use "Reexibir" no painel.
        </p>
    @endif

    <p style="margin:0 0 16px;">
        <strong>Tipo:</strong> {{ $targetLabel }}<br>
        <strong>Post:</strong> {{ $postTitle }}<br>
        <strong>Autor do conteúdo:</strong> {{ $targetAuthor }}<br>
        <strong>Denunciado por:</strong> {{ $reporterName }}<br>
        <strong>Motivo:</strong> {{ $reason ?: '(não informado)' }}<br>
        <strong>Total de denúncias:</strong> {{ $reportCount }}
    </p>

    <p style="margin:0 0 16px;padding:12px 16px;background-color:#f4f4f5;border-left:3px solid #4f46e5;border-radius:4px;">
        {!! nl2br(e($targetContent)) !!}
    </p>

    <p style="margin:0 0 16px;">
        Ver no site:<br>
        <a href="{{ $contentUrl }}" style="color:#4f46e5;">{{ $contentUrl }}</a>
    </p>

    <p style="margin:0 0 16px;">
        Moderar no painel:<br>
        <a href="{{ $adminUrl }}" style="color:#4f46e5;">{{ $adminUrl }}</a>
    </p>
</body>
</html>
