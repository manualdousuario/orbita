Olá{{ !empty($displayName) ? ', '.$displayName : '' }},

Obrigado por se registrar no {{ config('orbita.name', 'Órbita') }}! Para ativar sua conta, precisamos confirmar seu endereço de email.

Clique no link abaixo para verificar seu email:
{{ $verificationUrl }}

Depois de ativar, você recebe notificações de respostas, menções e novos comentários nos posts que acompanha, no site e por email. Para desligar qualquer uma delas, acesse as opções do seu perfil:
{{ $preferencesUrl }}

Este link é válido por 24 horas. Se você não solicitou este registro, pode ignorar este email.
