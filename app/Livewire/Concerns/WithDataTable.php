<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Search, whitelisted sorting and pagination for list screens.
 *
 * Components declare `protected array $sortable = ['column', ...]` and call
 * `$this->applySorting($query)` / `$this->paginateQuery($query)`.
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sortBy = '';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortable ?? [], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySorting(Builder $query, string $default = 'id', string $defaultDirection = 'desc'): Builder
    {
        if (in_array($this->sortBy, $this->sortable ?? [], true)) {
            return $query->orderBy($this->sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query->orderBy($default, $defaultDirection);
    }

    protected function searchTerm(): ?string
    {
        $term = trim($this->search);

        return $term === '' ? null : '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
