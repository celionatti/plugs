<?php

declare(strict_types=1);

namespace Plugs\View\Components;

use Plugs\View\Component;

/**
 * Built-in Pagination Component (Class-Backed)
 *
 * Computes page ranges, previous/next URLs, and ellipsis positions.
 * The view template at src/View/components/pagination.plug.php handles rendering.
 */
class Pagination extends Component
{
    public int $currentPage = 1;
    public int $totalPages = 1;
    public string $baseUrl = '';
    public int $maxVisible = 7;

    /** @var array Computed page items */
    public array $pages = [];
    public ?string $prevUrl = null;
    public ?string $nextUrl = null;

    public function __construct(
        int $currentPage = 1,
        int $totalPages = 1,
        string $baseUrl = '',
        int $maxVisible = 7,
    ) {
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = max(1, $totalPages);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->maxVisible = max(3, $maxVisible);

        $this->computePages();
    }

    /**
     * Compute page links with ellipsis logic.
     */
    private function computePages(): void
    {
        if ($this->totalPages <= 1) {
            return;
        }

        $half = (int) floor($this->maxVisible / 2);
        $start = max(1, $this->currentPage - $half);
        $end = min($this->totalPages, $start + $this->maxVisible - 1);

        if ($end - $start < $this->maxVisible - 1) {
            $start = max(1, $end - $this->maxVisible + 1);
        }

        $this->pages = [];

        // First page + ellipsis
        if ($start > 1) {
            $this->pages[] = ['page' => 1, 'url' => $this->pageUrl(1), 'type' => 'link'];
            if ($start > 2) {
                $this->pages[] = ['type' => 'ellipsis'];
            }
        }

        // Visible pages
        for ($i = $start; $i <= $end; $i++) {
            $this->pages[] = [
                'page' => $i,
                'url' => $this->pageUrl($i),
                'type' => $i === $this->currentPage ? 'current' : 'link',
            ];
        }

        // Last page + ellipsis
        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) {
                $this->pages[] = ['type' => 'ellipsis'];
            }
            $this->pages[] = ['page' => $this->totalPages, 'url' => $this->pageUrl($this->totalPages), 'type' => 'link'];
        }

        // Prev / Next
        $this->prevUrl = $this->currentPage > 1 ? $this->pageUrl($this->currentPage - 1) : null;
        $this->nextUrl = $this->currentPage < $this->totalPages ? $this->pageUrl($this->currentPage + 1) : null;
    }

    /**
     * Build a URL for a given page number.
     */
    private function pageUrl(int $page): string
    {
        $separator = str_contains($this->baseUrl, '?') ? '&' : '?';
        return $this->baseUrl . $separator . 'page=' . $page;
    }

    public function render(): string
    {
        return 'pagination';
    }
}
