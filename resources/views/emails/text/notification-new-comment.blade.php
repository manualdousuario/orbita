Olá, {{ $authorName }},

{{ $commenterName }} comentou {{ $following ? 'no post que você acompanha' : 'no seu post' }}: {{ $postTitle }}

"{{ \Illuminate\Support\Str::limit($commentContent, 300) }}"

Para responder, acesse:
{{ $commentLink }}
