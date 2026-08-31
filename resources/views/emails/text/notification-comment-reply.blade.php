Olá, {{ $authorName }},

{{ $replierName }} respondeu ao seu comentário em: {{ $postTitle }}

Seu comentário:
"{{ \Illuminate\Support\Str::limit($parentContent, 150) }}"

Resposta:
"{{ \Illuminate\Support\Str::limit($replyContent, 300) }}"

Para responder, acesse:
{{ $replyLink }}
