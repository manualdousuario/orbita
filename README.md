# Órbita

Um agregador no espírito do Hacker News!

Instancia publica: [orbita.social.br](https://orbita.social.br).

## Docker

### 1. Volumes

```bash
sudo mkdir -p /opt/orbita
cd /opt/orbita
sudo mkdir -p storage/framework/{views,sessions,cache/data} storage/logs
sudo chown -R www-data:www-data storage/
sudo mkdir -p valkey && sudo chown -R 999:1000 valkey/
```

### 2. Baixe o `compose.yml` e o `.env`

```bash
curl -O https://raw.githubusercontent.com/manualdousuario/orbita/main/compose.yml
curl -o .env https://raw.githubusercontent.com/manualdousuario/orbita/main/.env.example
```

### 3. Preencha o `.env`

Abra o arquivo e ajuste:

- `APP_URL` com o endereço público do site
- `MARIADB_PASSWORD` e `DB_PASSWORD` com a **mesma** senha
- `MARIADB_ROOT_PASSWORD` com outra senha
- `REDIS_PASSWORD` com mais uma ;)

Gere a chave da aplicação em https://laravel-encryption-key-generator.vercel.app/

- `APP_KEY`

### 4. Suba

```bash
docker compose up -d
```

### 5. Primeiro acesso

```bash
docker compose exec orbita php artisan db:seed
```

> **Troque a senha do admin antes de abrir o site.**
> Esse comando cria o usuário administrador com credenciais fixas e públicas:
> `orbita@orbita.social.br`, senha `orbita`.
> Entre em `/admin` e mude a senha (e o e-mail) na hora.

### 6. Acesse

O site fica em `http://SEU_SERVIDOR:8080` e o painel em `http://SEU_SERVIDOR:8080/admin`.

### Sobre HTTPS

O `.env.example` vem com `SESSION_SECURE_COOKIE=true`, o que faz o cookie de sessão só trafegar por
HTTPS. Em produção é isso mesmo. Se você estiver testando em `http://` puro, o login não vai
funcionar até trocar para `false`.

### Proxy Reverso

Se você usa NGINX Proxy Manager, Traefik ou algo parecido rodando na própria rede Docker, descomente
o bloco `reverse-proxy` no `compose.yml` e crie a rede antes de subir:

```bash
docker network create reverse-proxy
```

## Configuração

No `.env` só fica o que precisa existir antes do site subir.

| Variável | Para quê |
|---|---|
| `APP_NAME`, `APP_URL`, `APP_TIMEZONE` | Nome, endereço público e fuso horário |
| `MARIADB_PASSWORD`, `DB_PASSWORD` | Senha do banco, precisam ser iguais |
| `MARIADB_ROOT_PASSWORD` | Senha de root do MariaDB |
| `REDIS_PASSWORD` | Senha do Valkey |
| `MAIL_MAILER` e as credenciais SMTP | Envio de e-mail |
| `MAIL_FROM_ADDRESS` | Remetente das mensagens |
| `ORBITA_REGISTER` | Deixa os cadastros abertos ou fechados |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Captcha do Cloudflare Turnstile, opcional |
| `GOOGLE_*`, `GITHUB_*`, `LINKEDIN_*`, `INSTAGRAM_*` | Login social, opcional |

> **Configure o e-mail.** Sem `MAIL_MAILER` preenchido, o Laravel usa `log` como padrão: os e-mails
> de verificação de conta, recuperação de senha e notificação vão parar num arquivo de log e ninguém
> recebe nada. Ninguém consegue confirmar o cadastro, e não aparece erro nenhum.

O `.env.example` traz as demais variáveis comentadas, com uma linha explicando cada uma.

## Atualizar

```bash
docker compose pull && docker compose up -d
```

Migrations novas rodam no boot, junto com o resto.

## Licença

GPL-3.0. O texto completo está em [LICENSE.md](LICENSE.md).
