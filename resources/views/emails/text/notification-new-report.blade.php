🚨 Nova denúncia
@if ($autoHidden)

Este conteúdo foi ocultado automaticamente.
Para reexibir, use "Reexibir" no painel.
@endif

Tipo: {{ $targetLabel }}
Post: {{ $postTitle }}
Autor do conteúdo: {{ $targetAuthor }}
Denunciado por: {{ $reporterName }}
Motivo: {{ $reason ?: '(não informado)' }}
Total de denúncias: {{ $reportCount }}

Conteúdo denunciado:
"{{ $targetContent }}"

Ver no site:
{{ $contentUrl }}

Moderar no painel:
{{ $adminUrl }}
