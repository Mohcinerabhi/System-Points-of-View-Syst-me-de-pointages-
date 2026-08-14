<?php
namespace App\Helpers;

/**
 * Pagination générique.
 */
class Paginator
{
    public int $total;
    public int $perPage;
    public int $page;
    public int $lastPage;
    public int $from;
    public int $to;

    public function __construct(int $total, int $page = 1, int $perPage = 20)
    {
        $this->total   = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->lastPage = (int) ceil($this->total / $this->perPage);
        $this->page    = max(1, min($page, max(1, $this->lastPage)));
        $this->from    = $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
        $this->to      = min($this->page * $this->perPage, $this->total);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function pages(): array
    {
        $pages = [];
        $start = max(1, $this->page - 2);
        $end   = min($this->lastPage, $this->page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        return $pages;
    }

    /**
     * Returns the list of pages to display in the pagination widget,
     * including ellipsis markers ('...') for skipped ranges.
     *
     * Example for page 10 of 182:
     *   [1, '...', 8, 9, 10, 11, 12, '...', 182]
     */
    public function pagesWithEllipsis(): array
    {
        if ($this->lastPage <= 1) {
            return [];
        }

        $delta = 2;

        if ($this->lastPage <= 2 * $delta + 5) {
            return range(1, $this->lastPage);
        }

        $pages = [];

        $pages[] = 1;

        $left  = max(2, $this->page - $delta);
        $right = min($this->lastPage - 1, $this->page + $delta);

        if ($left > 2) {
            $pages[] = '...';
        }

        for ($i = $left; $i <= $right; $i++) {
            $pages[] = $i;
        }

        if ($right < $this->lastPage - 1) {
            $pages[] = '...';
        }

        $pages[] = $this->lastPage;

        return $pages;
    }

    public function toArray(): array
    {
        return [
            'total'               => $this->total,
            'per_page'            => $this->perPage,
            'current'             => $this->page,
            'last'                => $this->lastPage,
            'from'                => $this->from,
            'to'                  => $this->to,
            'pages'               => $this->pages(),
            'pages_with_ellipsis' => $this->pagesWithEllipsis(),
        ];
    }
}
