<?php

namespace App\Livewire\Concerns;

use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

trait InteractsWithInfiniteScroll
{
    use WithoutUrlPagination;
    use WithPagination;

    abstract protected function rowsIsland(string $pageName = 'page'): string;

    abstract protected function navIsland(string $pageName = 'page'): string;

    public function paginationView(): string
    {
        return 'partials.pagination';
    }

    public function paginationSimpleView(): string
    {
        return 'partials.pagination';
    }

    public function loadMore(string $pageName = 'page'): void
    {
        $this->setPage($this->getPage($pageName) + 1, $pageName);

        $this->renderIsland(name: $this->navIsland($pageName), mode: 'morph');
    }

    protected function resetInfinite(string $pageName = 'page'): void
    {
        $this->resetPage($pageName);

        $this->renderIsland(name: $this->rowsIsland($pageName), mode: 'morph');
        $this->renderIsland(name: $this->navIsland($pageName), mode: 'morph');

        $this->skipRender();
    }
}
