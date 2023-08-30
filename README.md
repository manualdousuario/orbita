# 🔭 Órbita

Plugin de WordPress para criar um painel de debates baseado em links, similar ao Hacker News, para o [Manual do Usuário](https://manualdousuario.net).

## Rodar o projeto

Requisitos
- Node.js

Clone o repositório

```bash
git clone git@github.com:gabrnunes/orbita.git
```

Instale as dependências

```bash
cd orbita
npm install
npm install --global grunt sass ruby
```

Caso queira alterar o arquivo de estilo `main.scss`

```bash
grunt watch
```

Gerar arquivo `orbita.zip`

```bash
grunt compress
```

## Shortcodes

`[orbita-form]`: formulário de postagem

`[orbita-ranking]`: ranking com posts mais votados

`[orbita-posts]`: listagem de post

`[orbita-header]`: menu

`[orbita-vote]`: componente de votoção

`[orbita-my-comments]`: listagem de todos os comentários do usuario

`[orbita-my-posts]`: listagem de todos os tópicos do usuario

## WP Cli

`wp orbita ogimage update [--posts_per_page=<posts_per_page>]`: busca pelas ogimage em posts com url externas sem thumbnail
```
[--posts_per_page=<posts_per_page>]
	quantidade de posts a serem verificados, se não preencher o valor padrão é 10
```

`wp orbita ogimage search [--posts_per_page=<posts_per_page>] [--external_url_ogimage=<external_url_ogimage>]`: busca por valores no campo external_url_ogimage
```
[--posts_per_page=<posts_per_page>]
	quantidade de posts a serem verificados, se não preencher o valor padrão é -1

[--external_url_ogimage=<external_url_ogimage>]
	filtrar informações no campo external_url_ogimage, guia de parametros de erros:
	// e_001 = error downloading source code from external url
	// e_002 = og:image not found after request
	// e_003 = error downloading image received in og:image
	// e_004 = did not recognize a mime type as an image extension
```

## Instalar o plugin

1. Faça login no seu admin do WordPress
2. Visite Plugins > Adicionar novo > Enviar plugin
3. "Escolher arquivo" e selecione o `orbita.zip`
4. Clique em "Instalar agora" e depois em "Ativar plugin"

## Contribuição

Você pode ajudar abrindo um pull request (verifique as issues abertas ou, se preferir, abra uma nova) ou reportando um bug.

Para manter o repositório padronizado:
- escreva as mensagens dos commits em português
- siga os [coding standards do WordPress](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)