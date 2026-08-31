<?php

namespace App\Support;

/**
 * Single source of truth for the home feed tabs and their ranking methods.
 */
class HomeFeedTabs
{
    public const DEFAULT = 'popular';

    /**
     * @return array<string, array{path: string, label: string, description: string, method: string, kind?: string}>
     */
    public static function all(): array
    {
        return [
            'popular' => [
                'path' => '/popular',
                'label' => 'Populares',
                'description' => 'Posts dos últimos 7 dias, ordenados por score de reações.',
                'method' => 'getPopularPosts',
            ],
            'all' => [
                'path' => '/all',
                'label' => 'Tudo',
                'description' => 'Todos os posts publicados, do mais recente para o mais antigo.',
                'method' => 'getAllPosts',
            ],
            'recent_comments' => [
                'path' => '/recent-comments',
                'label' => 'Atividade recente',
                'description' => 'Posts com os comentários mais recentes.',
                'method' => 'getPostsByRecentComments',
            ],
            'reactions' => [
                'path' => '/more-reactions',
                'label' => 'Mais reações',
                'description' => 'Posts que mais receberam reações.',
                'method' => 'getPostsByReactionsOnly',
            ],
            'comments' => [
                'path' => '/more-comments',
                'label' => 'Mais comentados',
                'description' => 'Posts com o maior número de comentários.',
                'method' => 'getPostsByCommentCount',
            ],
            'no_comments' => [
                'path' => '/no-comments',
                'label' => 'Sem comentários',
                'description' => 'Posts que ainda não receberam nenhum comentário.',
                'method' => 'getPostsWithoutComments',
            ],
            'all_comments' => [
                'path' => '/all-comments',
                'label' => 'Comentários',
                'description' => 'Todos os comentários de todos os posts, do mais recente para o mais antigo.',
                'method' => 'getAllComments',
                'kind' => 'comments',
            ],
        ];
    }

    public static function exists(string $tab): bool
    {
        return array_key_exists($tab, self::all());
    }

    /** @return array{path: string, label: string, description: string, method: string, kind?: string} */
    public static function get(string $tab): array
    {
        return self::all()[$tab] ?? self::all()[self::defaultTab()];
    }

    /**
     * The site's main tab, validated against all().
     */
    public static function defaultTab(): string
    {
        $tab = (string) config('orbita.home.default_tab', self::DEFAULT);

        return self::exists($tab) ? $tab : self::DEFAULT;
    }

    /** The RankingService method behind the main tab - the only feed where is_pinned sorts. */
    public static function defaultMethod(): string
    {
        return self::all()[self::defaultTab()]['method'];
    }

    /** Route name for a tab, e.g. `home.popular`. */
    public static function routeName(string $tab): string
    {
        return 'home.'.str_replace('_', '-', $tab);
    }
}
