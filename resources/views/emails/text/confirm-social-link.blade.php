Olá{{ !empty($displayName) ? ', '.$displayName : '' }},

Alguém entrou com uma conta do {{ $providerLabel }} que usa o mesmo email da sua conta no {{ config('orbita.name', 'Órbita') }}.

Se foi você, acesse o link abaixo para conectar as duas contas. Depois disso você poderá entrar pelo {{ $providerLabel }} sem digitar sua senha:
{{ $confirmUrl }}

Este link expirará em 24 horas. Se não foi você, ignore este email. Nada foi conectado à sua conta e ninguém entrou nela.
