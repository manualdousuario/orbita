@php
    $appName = config('orbita.name', config('app.name', 'Órbita'));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? $appName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b; -webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e4e4e7;">
                    <tr>
                        <td style="background-color:#4f46e5; padding:20px 32px;">
                            <h1 style="margin:0; font-size:20px; line-height:1.2; color:#ffffff; font-weight:700;">{{ $appName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; font-size:15px; line-height:1.6; color:#3f3f46;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #e4e4e7; font-size:14px; line-height:1.5; color:#a1a1aa;">
                            Este é um email automático de {{ $appName }}. Por favor, não responda diretamente a esta mensagem.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
