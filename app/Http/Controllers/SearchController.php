<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Full-text search over posts and comments.
 */
class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        if (! in_array($type, ['all', 'posts', 'comments'], true)) {
            $type = 'all';
        }

        $results = null;
        $total = 0;

        if ($query !== '') {
            $results = $search->search($query, [
                'type' => $type,
                'limit' => (int) config('orbita.pagination.search_limit'),
                'date_from' => $request->query('date_from') ?: null,
                'date_to' => $request->query('date_to') ?: null,
            ]);
            $total = count($results['posts']) + count($results['comments']);
        }

        return view('search', [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'total' => $total,
        ]);
    }
}
