<?php

namespace App\Support;

/**
 * Declares the admin-editable settings, their types, and casting.
 */
class SettingsRegistry
{
    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_STRING = 'string';

    public const TYPE_SELECT = 'select';

    public const TYPE_TEXT = 'text';

    /**
     * Declares every editable setting key with type, group, and label.
     *
     * @return array<string, array{type: string, group: string, label: string, help?: string}>
     */
    public static function definitions(): array
    {
        return [
            'orbita.name' => ['type' => self::TYPE_STRING, 'group' => 'geral', 'label' => 'Nome do site', 'help' => 'Aparece no cabeçalho, no título das páginas e nos e-mails enviados pelo sistema.'],
            'orbita.register' => ['type' => self::TYPE_BOOL, 'group' => 'geral', 'label' => 'Permitir novos cadastros', 'help' => 'Desligado, a página de cadastro deixa de aceitar inscrições e só quem já tem conta consegue entrar.'],
            'orbita.avatar_style' => ['type' => self::TYPE_SELECT, 'group' => 'geral', 'label' => 'Estilo do avatar automático', 'options' => self::dicebearStyles(), 'help' => 'Desenho usado quando a pessoa não enviou foto. O avatar é gerado a partir do nome de usuário.'],
            'orbita.avatar_gravatar_enabled' => ['type' => self::TYPE_BOOL, 'group' => 'geral', 'label' => 'Buscar avatar no Gravatar', 'help' => 'Quem não enviou foto recebe automaticamente a imagem ligada ao seu e-mail no Gravatar. A imagem é baixada e guardada no servidor.'],
            'orbita.limit_images_post' => ['type' => self::TYPE_INT, 'group' => 'geral', 'label' => 'Máximo de imagens por post', 'help' => 'Quantas imagens pode anexar em um post.'],
            'orbita.home.default_tab' => ['type' => self::TYPE_SELECT, 'group' => 'geral', 'label' => 'Aba padrão do feed', 'options' => self::homeFeedTabs(), 'help' => 'Aba aberta quando alguém chega ao site. É a única em que posts fixados ficam no topo.'],
            'orbita.meta.max_description_length' => ['type' => self::TYPE_INT, 'group' => 'geral', 'label' => 'Tamanho máximo da descrição (caracteres)', 'help' => 'Limite da descrição enviada a buscadores e redes sociais. O que passar disso é cortado com reticências.'],
            'orbita.meta.default_description' => ['type' => self::TYPE_STRING, 'group' => 'geral', 'label' => 'Descrição padrão do site', 'help' => 'Texto usado por buscadores e redes sociais quando a página não tem descrição própria.'],
            'orbita.meta.third_party_code' => ['type' => self::TYPE_TEXT, 'group' => 'geral', 'label' => 'Código de terceiro', 'help' => 'Código HTML inserido em todas as páginas, dentro do <head>.'],
            'orbita.quotes' => ['type' => self::TYPE_TEXT, 'group' => 'geral', 'label' => 'Frases do rodapé', 'help' => 'Uma frase por linha, no formato TEXTO | AUTOR. O autor é opcional. A cada carregamento, uma frase da lista é sorteada. Linhas em branco são ignoradas.'],
            'orbita.sitemap.posts_per_file' => ['type' => self::TYPE_INT, 'group' => 'geral', 'label' => 'Posts por arquivo do sitemap', 'help' => 'Quantos posts entram em cada arquivo do sitemap. Números menores geram mais arquivos, porém mais leves.'],
            'orbita.robots.content' => ['type' => self::TYPE_TEXT, 'group' => 'geral', 'label' => 'Conteúdo do robots.txt', 'help' => 'Regras entregues aos buscadores em /robots.txt, uma por linha. A linha Sitemap: é acrescentada sozinha, com o endereço completo do site, e não precisa ser escrita aqui.'],

            'orbita.posts.per_page' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Posts por página', 'help' => 'Quantos posts aparecem por página nas listagens do site.'],
            'orbita.posts.image_max_width' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Largura máxima da imagem (px)', 'help' => 'Imagens enviadas mais largas que isso são reduzidas no envio. A altura acompanha, mantendo a proporção.'],
            'orbita.posts.image_max_size' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Tamanho máximo da imagem (bytes)', 'help' => 'Tamanho máximo aceito por imagem enviada, em bytes. 10485760 equivale a 10 MB.'],
            'orbita.posts.score_decay_hours' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Perder 1 ponto a cada (horas)', 'help' => 'De quantas em quantas horas um post perde 1 ponto no ranking. Quanto menor o número, mais rápido o conteúdo antigo sai da frente.'],
            'orbita.posts.edit_time_limit' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Janela de edição (segundos)', 'help' => 'Segundos que o autor tem para editar o post depois de publicar. 900 equivale a 15 minutos.'],
            'orbita.posts.close_inactive_days' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Fechar posts inativos após (dias)', 'help' => 'Dias sem nenhum comentário até o post parar de aceitar comentários novos. O post continua visível. Use 0 para nunca fechar.'],
            'orbita.posts.limit_tags' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Máximo de tags por post', 'help' => 'Quantas hashtags escritas no texto viram tags do post.'],
            'orbita.posts.vote_allow_negative' => ['type' => self::TYPE_BOOL, 'group' => 'posts', 'label' => 'Permitir score negativo'],
            'orbita.posts.show_score' => ['type' => self::TYPE_BOOL, 'group' => 'posts', 'label' => 'Exibir pontuação', 'help' => 'Mostra o número de pontos do post nas listagens e na página dele. Desligado, o número some da tela, mas continua sendo calculado e usado na ordenação.'],
            'orbita.posts.enable_paywall_bypass' => ['type' => self::TYPE_BOOL, 'group' => 'posts', 'label' => 'Contornar paywall em links', 'help' => 'Liga o redirecionamento de links pelas regras do campo abaixo. Sem nenhuma regra cadastrada, nada muda.'],
            'orbita.posts.paywall_bypass_rules' => ['type' => self::TYPE_TEXT, 'group' => 'posts', 'label' => 'Regras por domínio', 'help' => 'Uma regra por linha, no formato dominio = servico. Ex.: exemplo.com = https://leitor.exemplo.org. Só os domínios listados (e seus subdomínios) são redirecionados; os demais ficam intocados. Se duas regras servirem, vence a de domínio mais específico. Linhas que começam com # são ignoradas.'],
            'orbita.posts.enable_auto_translate' => ['type' => self::TYPE_BOOL, 'group' => 'posts', 'label' => 'Ativar link de tradução', 'help' => 'Adiciona no post um link que abre a página de destino traduzida, usando o modelo de endereço do campo abaixo.'],
            'orbita.posts.translate_base_url' => ['type' => self::TYPE_STRING, 'group' => 'posts', 'label' => 'Modelo de endereço do tradutor', 'help' => 'Escreva {url} onde o link do post deve entrar; ele é inserido codificado. Ex.: https://tradutor.exemplo.com/?destino=pt&u={url}. Em branco, ou sem o {url}, o link de tradução não aparece mesmo com a opção acima ligada.'],
            'orbita.posts.max_title' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Título máximo (caracteres)', 'help' => 'Quantos caracteres o título de um post pode ter.'],
            'orbita.posts.max_content' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Conteúdo máximo (caracteres)', 'help' => 'Quantos caracteres o texto de um post pode ter.'],
            'orbita.posts.max_url_length' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Tamanho máximo do link (caracteres)', 'help' => 'Links maiores que isso são recusados no envio.'],
            'orbita.pagination.profile_per_page' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Posts por página no perfil', 'help' => 'Quantos posts aparecem por página na página de perfil de cada pessoa.'],
            'orbita.pagination.notifications_per_page' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Notificações por página', 'help' => 'Quantas notificações aparecem por página.'],
            'orbita.pagination.bookmarks_per_page' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Posts acompanhados por página', 'help' => 'Quantos posts acompanhados aparecem por página.'],
            'orbita.pagination.feed_limit' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Itens no feed RSS', 'help' => 'Quantos posts mais recentes entram no feed RSS.'],
            'orbita.pagination.search_limit' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Resultados de busca por grupo', 'help' => 'Quantos resultados a busca mostra em cada tipo de conteúdo.'],
            'orbita.pagination.search_max_limit' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Limite máximo de resultados', 'help' => 'Teto que a busca nunca ultrapassa, mesmo que um pedido peça mais.'],
            'orbita.notifications.read_retention_days' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Guardar notificações lidas por (dias)', 'help' => 'Passado esse tempo, as notificações já lidas são apagadas por uma tarefa automática.'],
            'orbita.notifications.unread_retention_days' => ['type' => self::TYPE_INT, 'group' => 'posts', 'label' => 'Guardar não lidas por (dias)', 'help' => 'Passado esse tempo, as notificações que ninguém abriu também são apagadas.'],

            'orbita.comments.per_page' => ['type' => self::TYPE_INT, 'group' => 'comentarios', 'label' => 'Comentários por página', 'help' => 'Quantos comentários aparecem por página na página do post.'],
            'orbita.comments.max_nesting_level' => ['type' => self::TYPE_INT, 'group' => 'comentarios', 'label' => 'Profundidade máxima de respostas', 'help' => 'Quantos níveis de resposta dentro de resposta a conversa aceita. Ao chegar no limite, as novas respostas continuam no mesmo nível, sem recuar mais.'],
            'orbita.comments.edit_time_limit' => ['type' => self::TYPE_INT, 'group' => 'comentarios', 'label' => 'Janela de edição (segundos)', 'help' => 'Segundos que o autor tem para editar o comentário depois de publicar. 900 equivale a 15 minutos.'],
            'orbita.comments.require_login' => ['type' => self::TYPE_BOOL, 'group' => 'comentarios', 'label' => 'Exigir login para comentar', 'help' => 'Ligado, só quem tem conta e está com a sessão aberta pode comentar.'],
            'orbita.comments.allow_images' => ['type' => self::TYPE_BOOL, 'group' => 'comentarios', 'label' => 'Permitir imagens em comentários', 'help' => 'Deixa anexar imagens dentro dos comentários.'],
            'orbita.comments.max_images' => ['type' => self::TYPE_INT, 'group' => 'comentarios', 'label' => 'Máximo de imagens por comentário', 'help' => 'Quantas imagens cabem em um comentário, quando o envio de imagens está ligado.'],
            'orbita.comments.default_sort' => ['type' => self::TYPE_SELECT, 'group' => 'comentarios', 'label' => 'Ordenação padrão', 'options' => ['newest' => 'Mais recentes', 'oldest' => 'Mais antigos', 'most_reactions' => 'Mais reações'], 'help' => 'Como os comentários aparecem ao abrir o post. Quem está lendo pode trocar a ordem depois.'],

            'orbita.antispam.post_cooldown' => ['type' => self::TYPE_INT, 'group' => 'antispam', 'label' => 'Espera mínima entre posts (segundos)', 'help' => 'Segundos que a pessoa precisa esperar depois de publicar um post para publicar o próximo. Use 0 para ignorar.'],
            'orbita.antispam.comment_cooldown' => ['type' => self::TYPE_INT, 'group' => 'antispam', 'label' => 'Espera mínima entre comentários (segundos)', 'help' => 'Tempo que a pessoa precisa esperar entre um comentário e o próximo. Use 0 para ignorar.'],
            'orbita.antispam.max_posts_per_hour' => ['type' => self::TYPE_INT, 'group' => 'antispam', 'label' => 'Máx. posts por hora', 'help' => 'Teto de posts que uma mesma pessoa publica por hora. Vale junto com a espera acima: as duas regras precisam ser respeitadas.'],
            'orbita.antispam.max_comments_per_hour' => ['type' => self::TYPE_INT, 'group' => 'antispam', 'label' => 'Máx. comentários por hora', 'help' => 'Teto de comentários que uma mesma pessoa publica por hora, somado à espera acima.'],
            'orbita.antispam.duplicate_detection' => ['type' => self::TYPE_BOOL, 'group' => 'antispam', 'label' => 'Detectar conteúdo duplicado', 'help' => 'Recusa um post ou comentário com o mesmo texto que a pessoa acabou de enviar.'],

            'orbita.moderation.auto_hide_threshold' => ['type' => self::TYPE_INT, 'group' => 'moderacao', 'label' => 'Ocultar automaticamente após (denúncias)', 'help' => 'Número de denúncias, de pessoas diferentes, que oculta o conteúdo sozinho. Use 0 para desligar.'],
            'orbita.moderation.strip_referral_params' => ['type' => self::TYPE_BOOL, 'group' => 'moderacao', 'label' => 'Remover códigos de referência dos links', 'help' => 'Ao publicar, os parâmetros listados abaixo são apagados dos links do post, dos comentários e do perfil. O conteúdo é salvo normalmente, só o link fica limpo, e a limpeza é registrada em Moderação.'],
            'orbita.moderation.forbidden_url_params' => ['type' => self::TYPE_TEXT, 'group' => 'moderacao', 'label' => 'Parâmetros de referência proibidos', 'help' => 'Um parâmetro por linha. Linhas que começam com # são ignoradas.'],
            'orbita.moderation.hide_referral_for_review' => ['type' => self::TYPE_BOOL, 'group' => 'moderacao', 'label' => 'Ocultar conteúdo com link de referência', 'help' => 'Além de limpar o link, deixa o conteúdo oculto até um moderador revisar.'],

            'orbita.password.min_length' => ['type' => self::TYPE_INT, 'group' => 'seguranca', 'label' => 'Tamanho mínimo da senha', 'help' => 'Quantos caracteres, no mínimo, a senha precisa ter no cadastro e na troca de senha.'],
            'orbita.turnstile.enabled' => ['type' => self::TYPE_BOOL, 'group' => 'seguranca', 'label' => 'Ativar verificação anti-robô', 'help' => 'Mostra um desafio anti-robô no cadastro e no login, usando o Cloudflare Turnstile. Só funciona com as chaves TURNSTILE_SITE_KEY e TURNSTILE_SECRET_KEY preenchidas no arquivo .env.'],
            'orbita.auth.max_login_attempts' => ['type' => self::TYPE_INT, 'group' => 'seguranca', 'label' => 'Tentativas de login antes do bloqueio', 'help' => 'Quantas tentativas de senha errada até novas tentativas serem recusadas pelo tempo definido abaixo.'],
            'orbita.auth.login_decay_seconds' => ['type' => self::TYPE_INT, 'group' => 'seguranca', 'label' => 'Janela de bloqueio (segundos)', 'help' => 'Quantos segundos o bloqueio dura depois de estourar as tentativas. 1800 equivale a 30 minutos.'],
            'orbita.auth.username_change_cooldown_days' => ['type' => self::TYPE_INT, 'group' => 'seguranca', 'label' => 'Espera para trocar o nome de usuário (dias)', 'help' => 'Quantos dias a pessoa precisa esperar entre uma troca de nome de usuário.'],

            'orbita.social.google.enabled' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'Google: permitir login', 'help' => 'Mostra o botão "Entrar com Google" nas telas de login e cadastro. Só funciona com GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET preenchidas no arquivo .env.'],
            'orbita.social.google.require_email_confirmation' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'Google: exigir confirmação de e-mail', 'help' => 'Ligado, quem entra pelo Google precisa clicar no link enviado por e-mail antes de publicar, comentar ou reagir. Desligado, quem chega com o e-mail já confirmado pelo provedor entra com a conta ativa. Quando o provedor não confirma o e-mail, a confirmação é exigida de qualquer jeito.'],
            'orbita.social.linkedin.enabled' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'LinkedIn: permitir login', 'help' => 'Mostra o botão "Entrar com LinkedIn". Além de LINKEDIN_CLIENT_ID e LINKEDIN_CLIENT_SECRET no .env, o aplicativo precisa do produto "Sign In with LinkedIn using OpenID Connect" aprovado no portal do LinkedIn.'],
            'orbita.social.linkedin.require_email_confirmation' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'LinkedIn: exigir confirmação de e-mail', 'help' => 'Ligado, quem entra pelo LinkedIn precisa clicar no link enviado por e-mail antes de publicar, comentar ou reagir. Desligado, quem chega com o e-mail já confirmado pelo provedor entra com a conta ativa.'],
            'orbita.social.github.enabled' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'GitHub: permitir login', 'help' => 'Mostra o botão "Entrar com GitHub". Só funciona com GITHUB_CLIENT_ID e GITHUB_CLIENT_SECRET preenchidas no arquivo .env.'],
            'orbita.social.github.require_email_confirmation' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'GitHub: exigir confirmação de e-mail', 'help' => 'Ligado, quem entra pelo GitHub precisa clicar no link enviado por e-mail antes de publicar, comentar ou reagir. O GitHub só informa o e-mail quando a pessoa tem um endereço principal já confirmado lá; quando não informa, o cadastro pede o e-mail e a confirmação é obrigatória.'],
            'orbita.social.instagram.enabled' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'Instagram: permitir login', 'help' => 'Mostra o botão "Entrar com Instagram". Exige INSTAGRAM_CLIENT_ID e INSTAGRAM_CLIENT_SECRET no .env, endereço de retorno em HTTPS e conta Instagram do tipo Business ou Creator ligada a um aplicativo da Meta. Contas pessoais não conseguem entrar.'],
            'orbita.social.instagram.require_email_confirmation' => ['type' => self::TYPE_BOOL, 'group' => 'social', 'label' => 'Instagram: exigir confirmação de e-mail', 'help' => 'O Instagram não informa o e-mail da pessoa, então o cadastro sempre pede o endereço e sempre exige a confirmação por e-mail, independente desta opção.'],

            'orbita.images.quality' => ['type' => self::TYPE_INT, 'group' => 'imagens', 'label' => 'Qualidade das imagens (1-100)', 'help' => 'O arquivo original é guardado intacto.'],
            'orbita.images.max_width' => ['type' => self::TYPE_INT, 'group' => 'imagens', 'label' => 'Largura máxima exibida (px)'],
            'orbita.images.avatar_max_size' => ['type' => self::TYPE_INT, 'group' => 'imagens', 'label' => 'Tamanho máx. do avatar (KB)', 'help' => 'Tamanho máximo do arquivo de foto de perfil, em kilobytes. 5120 equivale a 5 MB.'],
            'orbita.images.avatar_size' => ['type' => self::TYPE_INT, 'group' => 'imagens', 'label' => 'Dimensão do avatar (px)', 'help' => 'Lado do quadrado em que a foto de perfil é recortada e salva.'],
            'orbita.images.avatar_quality' => ['type' => self::TYPE_INT, 'group' => 'imagens', 'label' => 'Qualidade do avatar (1-100)', 'help' => 'Compressão aplicada à foto de perfil depois do recorte.'],
            'orbita.ogimage.description_length' => ['type' => self::TYPE_INT, 'group' => 'imagens', 'label' => 'Descrição do compartilhamento (caracteres)', 'help' => 'Quantos caracteres do texto do post são aproveitados na descrição e na imagem geradas ao compartilhar o link.'],
        ];
    }

    /**
     * Home feed tabs (slug => label), from the single source in HomeFeedTabs.
     *
     * @return array<string, string>
     */
    public static function homeFeedTabs(): array
    {
        return array_map(static fn (array $meta): string => $meta['label'], HomeFeedTabs::all());
    }

    /** DiceBear 10.x avatar styles (slug => label). See https://www.dicebear.com/styles/ */
    public static function dicebearStyles(): array
    {
        return [
            'adventurer' => 'Adventurer',
            'adventurer-neutral' => 'Adventurer Neutral',
            'avataaars' => 'Avataaars',
            'avataaars-neutral' => 'Avataaars Neutral',
            'big-ears' => 'Big Ears',
            'big-ears-neutral' => 'Big Ears Neutral',
            'big-smile' => 'Big Smile',
            'bottts' => 'Bottts',
            'bottts-neutral' => 'Bottts Neutral',
            'croodles' => 'Croodles',
            'croodles-neutral' => 'Croodles Neutral',
            'disco' => 'Disco',
            'dylan' => 'Dylan',
            'fun-emoji' => 'Fun Emoji',
            'glass' => 'Glass',
            'glyphs' => 'Glyphs',
            'icons' => 'Icons',
            'identicon' => 'Identicon',
            'initial-face' => 'Initial Face',
            'initials' => 'Initials',
            'lorelei' => 'Lorelei',
            'lorelei-neutral' => 'Lorelei Neutral',
            'micah' => 'Micah',
            'miniavs' => 'Miniavs',
            'notionists' => 'Notionists',
            'notionists-neutral' => 'Notionists Neutral',
            'open-peeps' => 'Open Peeps',
            'personas' => 'Personas',
            'pixel-art' => 'Pixel Art',
            'pixel-art-neutral' => 'Pixel Art Neutral',
            'rings' => 'Rings',
            'shape-grid' => 'Shape Grid',
            'shapes' => 'Shapes',
            'stripes' => 'Stripes',
            'thumbs' => 'Thumbs',
            'toon-head' => 'Toon Head',
            'triangles' => 'Triangles',
        ];
    }

    /** @return array<string, string> group key => pt-BR heading */
    public static function groups(): array
    {
        return [
            'geral' => 'Geral',
            'posts' => 'Posts',
            'comentarios' => 'Comentários',
            'antispam' => 'Antispam',
            'moderacao' => 'Moderação',
            'seguranca' => 'Segurança e acesso',
            'social' => 'Login social',
            'imagens' => 'Imagens',
        ];
    }

    public static function isEditable(string $configKey): bool
    {
        return array_key_exists($configKey, self::definitions());
    }

    /** Casts a stored string back to its declared type. */
    public static function cast(string $configKey, ?string $raw): mixed
    {
        $type = self::definitions()[$configKey]['type'] ?? self::TYPE_STRING;

        return match ($type) {
            self::TYPE_BOOL => filter_var($raw, FILTER_VALIDATE_BOOL),
            self::TYPE_INT => (int) $raw,
            default => (string) $raw,
        };
    }

    /** Serializes a typed value for storage. */
    public static function serialize(string $configKey, mixed $value): string
    {
        $type = self::definitions()[$configKey]['type'] ?? self::TYPE_STRING;

        return match ($type) {
            self::TYPE_BOOL => $value ? '1' : '0',
            self::TYPE_INT => (string) (int) $value,
            default => (string) $value,
        };
    }
}
