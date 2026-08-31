Olá, {{ $mentionedUserName }},

Você foi mencionado no {{ config('orbita.name', 'Órbita') }}:

"{{ \Illuminate\Support\Str::limit($content, 250) }}"

Para ver o conteúdo completo, acesse:
{{ $link }}
