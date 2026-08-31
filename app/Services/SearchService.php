<?php

namespace App\Services;

use App\Support\Url;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Full-text searches posts and comments with date filters and pagination.
 */
class SearchService
{
    /**
     * Searches posts and comments, filtered by type and date.
     *
     * @param  array<string, mixed>  $filters
     * @return array{posts: array<int, object>, comments: array<int, object>}
     */
    public function search(string $query, array $filters = []): array
    {
        $results = [
            'posts' => [],
            'comments' => [],
        ];

        if (empty($filters['type']) || $filters['type'] === 'posts' || $filters['type'] === 'all') {
            $results['posts'] = $this->searchPosts($query, $filters);
        }

        if (empty($filters['type']) || $filters['type'] === 'comments' || $filters['type'] === 'all') {
            $results['comments'] = $this->searchComments($query, $filters);
        }

        return $results;
    }

    private function booleanQuery(string $query): string
    {
        $query = str_replace(
            ['+', '-', '@', '~', '<', '>', '(', ')', '"', '*'],
            ' ',
            $query,
        );

        return trim(preg_replace('/\s+/u', ' ', $query) ?? '');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function searchPostsPaginated(string $query, array $filters = [], int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->searchPostsFulltextPaginated($query, $filters, $perPage, $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function searchUnifiedPaginated(string $query, array $filters = [], int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return $this->searchUnifiedFulltextPaginated($query, $filters, $perPage, $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function searchUnifiedFulltextPaginated(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->booleanQuery($query);

        $perPage = max(1, min($perPage, (int) config('orbita.pagination.search_max_limit')));
        $page = max(1, $page);

        $type = $filters['type'] ?? 'all';
        $offset = ($page - 1) * $perPage;

        $postWhere = ["p.status = 'published'", 'p.deleted_at IS NULL', 'MATCH(p.title, p.content) AGAINST (? IN BOOLEAN MODE)'];
        $postParams = [$query];

        if (! empty($filters['date_from'])) {
            $postWhere[] = 'p.created_at >= ?';
            $postParams[] = $filters['date_from'];
        }
        if (! empty($filters['date_to'])) {
            $postWhere[] = 'p.created_at <= ?';
            $postParams[] = $filters['date_to'];
        }
        $postClause = implode(' AND ', $postWhere);

        $commentWhere = ["c.status = 'visible'", 'c.deleted_at IS NULL', 'MATCH(c.content) AGAINST (? IN BOOLEAN MODE)'];
        $commentParams = [$query];

        if (! empty($filters['date_from'])) {
            $commentWhere[] = 'c.created_at >= ?';
            $commentParams[] = $filters['date_from'];
        }
        if (! empty($filters['date_to'])) {
            $commentWhere[] = 'c.created_at <= ?';
            $commentParams[] = $filters['date_to'];
        }
        $commentClause = implode(' AND ', $commentWhere);

        $unions = [];
        $unionParams = [];

        if ($type === 'all' || $type === 'posts') {
            $unions[] = "SELECT 'post' AS type, p.hashid, p.slug, p.title, p.content, NULL AS post_hashid, NULL AS post_slug, u.username, u.display_name, u.avatar_url, u.anonymized_at, p.created_at, MATCH(p.title, p.content) AGAINST (? IN BOOLEAN MODE) AS relevance FROM posts p JOIN users u ON p.user_id = u.id WHERE {$postClause}";
            $unionParams = array_merge($unionParams, [$query], $postParams);
        }

        if ($type === 'all' || $type === 'comments') {
            $unions[] = "SELECT 'comment' AS type, c.hashid, p.slug, p.title, c.content, p.hashid AS post_hashid, p.slug AS post_slug, u.username, u.display_name, u.avatar_url, u.anonymized_at, c.created_at, MATCH(c.content) AGAINST (? IN BOOLEAN MODE) AS relevance FROM comments c JOIN users u ON c.user_id = u.id JOIN posts p ON c.post_id = p.id WHERE {$commentClause}";
            $unionParams = array_merge($unionParams, [$query], $commentParams);
        }

        if (empty($unions)) {
            return new LengthAwarePaginator([], 0, $perPage, $page, [
                'path' => Url::paginationPath(),
                'pageName' => 'page',
            ]);
        }

        $unionSql = implode(' UNION ALL ', $unions);

        $sql = "SELECT * FROM ({$unionSql}) AS combined ORDER BY relevance DESC, created_at DESC LIMIT ? OFFSET ?";
        $items = DB::select($sql, array_merge($unionParams, [$perPage, $offset]));

        $countSql = "SELECT COUNT(*) as n FROM ({$unionSql}) AS combined";
        $countRow = DB::selectOne($countSql, $unionParams);
        $total = (int) ($countRow->n ?? 0);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Url::paginationPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function searchPostsFulltextPaginated(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = $this->booleanQuery($query);

        $where = ["p.status = 'published'", 'p.deleted_at IS NULL'];
        $where[] = 'MATCH(p.title, p.content) AGAINST (? IN BOOLEAN MODE)';
        $whereParams = [$query];

        if (! empty($filters['date_from'])) {
            $where[] = 'p.created_at >= ?';
            $whereParams[] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[] = 'p.created_at <= ?';
            $whereParams[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                p.*,
                u.username,
                u.display_name,
                u.avatar_url,
                u.anonymized_at,
                MATCH(p.title, p.content) AGAINST (? IN BOOLEAN MODE) as relevance
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE {$whereClause}
            ORDER BY relevance DESC, p.created_at DESC
            LIMIT ? OFFSET ?";

        $items = DB::select($sql, array_merge([$query], $whereParams, [$perPage, $offset]));

        $countSql = "SELECT COUNT(*) as n FROM posts p WHERE {$whereClause}";
        $countRow = DB::selectOne($countSql, $whereParams);
        $total = (int) ($countRow->n ?? 0);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Url::paginationPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, object>
     */
    private function searchPosts(string $query, array $filters): array
    {
        $query = $this->booleanQuery($query);

        $where = ["p.status = 'published'", 'p.deleted_at IS NULL'];
        $where[] = 'MATCH(p.title, p.content) AGAINST (? IN BOOLEAN MODE)';
        $whereParams = [$query];

        if (! empty($filters['date_from'])) {
            $where[] = 'p.created_at >= ?';
            $whereParams[] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[] = 'p.created_at <= ?';
            $whereParams[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $limit = max(1, min((int) ($filters['limit'] ?? config('orbita.pagination.search_limit')), (int) config('orbita.pagination.search_max_limit')));

        $sql = "SELECT
                p.*,
                u.username,
                u.display_name,
                u.avatar_url,
                u.anonymized_at,
                MATCH(p.title, p.content) AGAINST (? IN BOOLEAN MODE) as relevance
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE {$whereClause}
            ORDER BY relevance DESC, p.created_at DESC
            LIMIT ?";

        return DB::select($sql, array_merge([$query], $whereParams, [$limit]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, object>
     */
    private function searchComments(string $query, array $filters): array
    {
        $query = $this->booleanQuery($query);

        $where = ["c.status = 'visible'", 'c.deleted_at IS NULL'];
        $where[] = 'MATCH(c.content) AGAINST (? IN BOOLEAN MODE)';
        $whereParams = [$query];

        if (! empty($filters['date_from'])) {
            $where[] = 'c.created_at >= ?';
            $whereParams[] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $where[] = 'c.created_at <= ?';
            $whereParams[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $limit = max(1, min((int) ($filters['limit'] ?? config('orbita.pagination.search_limit')), (int) config('orbita.pagination.search_max_limit')));

        $sql = "SELECT
                c.*,
                u.username,
                u.display_name,
                u.avatar_url,
                u.anonymized_at,
                p.title as post_title,
                p.hashid as post_hashid,
                p.slug as post_slug,
                MATCH(c.content) AGAINST (? IN BOOLEAN MODE) as relevance
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN posts p ON c.post_id = p.id
            WHERE {$whereClause}
            ORDER BY relevance DESC, c.created_at DESC
            LIMIT ?";

        return DB::select($sql, array_merge([$query], $whereParams, [$limit]));
    }
}
