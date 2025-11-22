<?php

declare(strict_types=1);

namespace Plugs\Paginator;

use Plugs\Database\Collection;

/**
 * Enhanced Pagination Class
 * 
 * A flexible pagination class with multiple render styles.
 * Fully compatible with PlugModel pagination.
 * 
 * @example
 * // Basic pagination
 * $users = User::paginate(15, 1);
 * echo $users['paginator']->render();
 * 
 * // Load more style
 * echo $users['paginator']->renderLoadMore();
 * 
 * // Simple prev/next
 * echo $users['paginator']->renderSimple();
 * 
 * // Numbers with ellipsis
 * echo $users['paginator']->renderWithEllipsis();
 */
class Pagination
{
    protected array $items = [];
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;
    protected int $from;
    protected int $to;
    protected ?string $path = null;
    protected array $query = [];
    protected array $options = [
        // Display options
        'show_numbers' => true,
        'show_first_last' => true,
        'show_prev_next' => true,
        'max_links' => 7,
        'ellipsis_enabled' => true,
        
        // CSS Classes
        'container_class' => 'pagination-container',
        'pagination_class' => 'pagination',
        'link_class' => 'page-link',
        'active_class' => 'active',
        'disabled_class' => 'disabled',
        'info_class' => 'pagination-info',
        'ellipsis_class' => 'page-ellipsis',
        
        // Text/Icons
        'prev_text' => '&laquo; Previous',
        'next_text' => 'Next &raquo;',
        'first_text' => '&laquo;&laquo; First',
        'last_text' => 'Last &raquo;&raquo;',
        'ellipsis_text' => '...',
        'load_more_text' => 'Load More',
        'loading_text' => 'Loading...',
        
        // Info format
        'info_format' => 'Showing {from} to {to} of {total} results',
        'info_format_single' => 'Showing {total} result',
        'info_format_empty' => 'No results found',
        
        // AJAX options
        'ajax_enabled' => false,
        'ajax_container' => '#results-container',
        'ajax_loader' => '.loader',
    ];

    /**
     * Constructor
     */
    public function __construct(array $items, int $perPage = 15, int $currentPage = 1, ?int $total = null)
    {
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->total = $total ?? count($items);
        $this->lastPage = (int) ceil($this->total / $this->perPage);
        $this->currentPage = min($this->currentPage, max(1, $this->lastPage));

        $offset = ($this->currentPage - 1) * $this->perPage;

        if ($total !== null) {
            $this->items = $items;
        } else {
            $this->items = array_slice($items, $offset, $this->perPage);
        }

        $this->from = $this->total > 0 ? $offset + 1 : 0;
        $this->to = min($offset + count($this->items), $this->total);

        $this->path = $this->getCurrentPath();
        $this->query = $this->getCurrentQuery();
    }

    /**
     * Create paginator from PlugModel query
     */
    public static function fromQuery($query, int $perPage = 15, int $currentPage = 1): self
    {
        $total = $query->count();
        $offset = ($currentPage - 1) * $perPage;
        $collection = $query->offset($offset)->limit($perPage)->get();
        
        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }

        return new self($items, $perPage, $currentPage, $total);
    }

    /**
     * Create paginator from array data in PlugModel format
     */
    public static function fromArray(array $paginationData): self
    {
        $data = $paginationData['data'] ?? [];
        
        // Handle Collection objects
        if ($data instanceof Collection) {
            $items = $data->all();
        } else {
            $items = is_array($data) ? $data : [];
        }
        
        $perPage = $paginationData['per_page'] ?? 15;
        $currentPage = $paginationData['current_page'] ?? 1;
        $total = $paginationData['total'] ?? count($items);

        return new self($items, $perPage, $currentPage, $total);
    }

    /**
     * Set pagination options
     */
    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Set base path
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Append query parameters
     */
    public function appends(array $query): self
    {
        $this->query = array_merge($this->query, $query);
        return $this;
    }

    /**
     * Get items
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int { return $this->total; }
    public function perPage(): int { return $this->perPage; }
    public function currentPage(): int { return $this->currentPage; }
    public function lastPage(): int { return $this->lastPage; }
    public function from(): int { return $this->from; }
    public function to(): int { return $this->to; }
    public function hasPages(): bool { return $this->lastPage > 1; }
    public function onFirstPage(): bool { return $this->currentPage <= 1; }
    public function onLastPage(): bool { return $this->currentPage >= $this->lastPage; }
    public function hasPreviousPage(): bool { return $this->currentPage > 1; }
    public function hasNextPage(): bool { return $this->currentPage < $this->lastPage; }
    public function previousPage(): ?int { return $this->hasPreviousPage() ? $this->currentPage - 1 : null; }
    public function nextPage(): ?int { return $this->hasNextPage() ? $this->currentPage + 1 : null; }

    /**
     * Build URL for a page
     */
    public function url(int $page): string
    {
        $query = array_merge($this->query, ['page' => $page]);
        $queryString = http_build_query($query);
        return $this->path . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Get page range with ellipsis support
     */
    protected function getPageRangeWithEllipsis(): array
    {
        $maxLinks = $this->options['max_links'];
        
        if ($this->lastPage <= $maxLinks + 2) {
            return [
                'pages' => range(1, $this->lastPage),
                'show_first_ellipsis' => false,
                'show_last_ellipsis' => false
            ];
        }

        $half = (int) floor($maxLinks / 2);
        $start = max(2, $this->currentPage - $half);
        $end = min($this->lastPage - 1, $start + $maxLinks - 1);

        if ($end - $start < $maxLinks - 1) {
            $start = max(2, $end - $maxLinks + 1);
        }

        return [
            'pages' => range($start, $end),
            'show_first_ellipsis' => $start > 2,
            'show_last_ellipsis' => $end < $this->lastPage - 1
        ];
    }

    /**
     * Get simple page range
     */
    protected function getPageRange(): array
    {
        $maxLinks = $this->options['max_links'];
        
        if ($this->lastPage <= $maxLinks) {
            return range(1, $this->lastPage);
        }

        $half = (int) floor($maxLinks / 2);
        $start = max(1, $this->currentPage - $half);
        $end = min($this->lastPage, $start + $maxLinks - 1);

        if ($end - $start < $maxLinks - 1) {
            $start = max(1, $end - $maxLinks + 1);
        }

        return range($start, $end);
    }

    /**
     * Get current path
     */
    protected function getCurrentPath(): string
    {
        return strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    }

    /**
     * Get current query
     */
    protected function getCurrentQuery(): array
    {
        $query = $_GET ?? [];
        unset($query['page']);
        return $query;
    }

    /**
     * Render info text
     */
    protected function renderInfo(): string
    {
        if ($this->total === 0) {
            return sprintf(
                '<div class="%s">%s</div>',
                $this->options['info_class'],
                $this->options['info_format_empty']
            );
        }

        if ($this->total === 1) {
            $text = str_replace('{total}', (string)$this->total, $this->options['info_format_single']);
        } else {
            $text = str_replace(
                ['{from}', '{to}', '{total}'],
                [$this->from, $this->to, $this->total],
                $this->options['info_format']
            );
        }

        return sprintf('<div class="%s">%s</div>', $this->options['info_class'], $text);
    }

    /**
     * Render standard pagination with all features
     */
    public function render(): string
    {
        if (!$this->hasPages()) {
            return $this->renderInfo();
        }

        $html = '<div class="' . $this->options['container_class'] . '">';
        $html .= $this->renderInfo();
        $html .= '<nav aria-label="Page navigation">';
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';
        
        // First
        if ($this->options['show_first_last']) {
            $html .= $this->renderFirstLink();
        }
        
        // Previous
        if ($this->options['show_prev_next']) {
            $html .= $this->renderPrevLink();
        }
        
        // Numbers
        if ($this->options['show_numbers']) {
            foreach ($this->getPageRange() as $page) {
                $html .= $page === $this->currentPage 
                    ? $this->renderActiveLink($page) 
                    : $this->renderPageLink($page);
            }
        }
        
        // Next
        if ($this->options['show_prev_next']) {
            $html .= $this->renderNextLink();
        }
        
        // Last
        if ($this->options['show_first_last']) {
            $html .= $this->renderLastLink();
        }
        
        $html .= '</ul></nav></div>';
        
        return $html;
    }

    /**
     * Render pagination with ellipsis (1 ... 5 6 7 ... 20)
     */
    public function renderWithEllipsis(): string
    {
        if (!$this->hasPages()) {
            return $this->renderInfo();
        }

        $range = $this->getPageRangeWithEllipsis();
        
        $html = '<div class="' . $this->options['container_class'] . '">';
        $html .= $this->renderInfo();
        $html .= '<nav aria-label="Page navigation">';
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';
        
        // Previous
        if ($this->options['show_prev_next']) {
            $html .= $this->renderPrevLink();
        }
        
        // First page
        $html .= $this->currentPage === 1 
            ? $this->renderActiveLink(1) 
            : $this->renderPageLink(1);
        
        // First ellipsis
        if ($range['show_first_ellipsis']) {
            $html .= $this->renderEllipsis();
        }
        
        // Middle pages
        foreach ($range['pages'] as $page) {
            $html .= $page === $this->currentPage 
                ? $this->renderActiveLink($page) 
                : $this->renderPageLink($page);
        }
        
        // Last ellipsis
        if ($range['show_last_ellipsis']) {
            $html .= $this->renderEllipsis();
        }
        
        // Last page
        if ($this->lastPage > 1) {
            $html .= $this->currentPage === $this->lastPage 
                ? $this->renderActiveLink($this->lastPage) 
                : $this->renderPageLink($this->lastPage);
        }
        
        // Next
        if ($this->options['show_prev_next']) {
            $html .= $this->renderNextLink();
        }
        
        $html .= '</ul></nav></div>';
        
        return $html;
    }

    /**
     * Render simple pagination (prev/next only)
     */
    public function renderSimple(): string
    {
        if (!$this->hasPages()) {
            return $this->renderInfo();
        }

        $html = '<div class="' . $this->options['container_class'] . ' pagination-simple">';
        $html .= $this->renderInfo();
        $html .= '<nav aria-label="Page navigation">';
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';
        
        $html .= $this->renderPrevLink();
        $html .= sprintf(
            '<li class="page-item"><span class="page-link">Page %d of %d</span></li>',
            $this->currentPage,
            $this->lastPage
        );
        $html .= $this->renderNextLink();
        
        $html .= '</ul></nav></div>';
        
        return $html;
    }

    /**
     * Render load more style pagination
     */
    public function renderLoadMore(): string
    {
        if (!$this->hasPages()) {
            return $this->renderInfo();
        }

        $html = '<div class="' . $this->options['container_class'] . ' pagination-load-more">';
        $html .= $this->renderInfo();
        
        if ($this->hasNextPage()) {
            $nextUrl = $this->url($this->nextPage());
            $loadMoreText = $this->options['load_more_text'];
            $loadingText = $this->options['loading_text'];
            
            if ($this->options['ajax_enabled']) {
                $html .= sprintf(
                    '<button class="btn-load-more" data-url="%s" data-page="%d" data-container="%s">%s</button>',
                    $nextUrl,
                    $this->nextPage(),
                    $this->options['ajax_container'],
                    $loadMoreText
                );
                $html .= sprintf('<div class="loading-indicator" style="display:none;">%s</div>', $loadingText);
            } else {
                $html .= sprintf('<a href="%s" class="btn-load-more">%s</a>', $nextUrl, $loadMoreText);
            }
        } else {
            $html .= '<div class="no-more-results">No more results</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render compact pagination (numbers only, no text)
     */
    public function renderCompact(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<nav aria-label="Page navigation" class="pagination-compact">';
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';
        
        $html .= $this->renderPrevLink('&lsaquo;');
        
        foreach ($this->getPageRange() as $page) {
            $html .= $page === $this->currentPage 
                ? $this->renderActiveLink($page) 
                : $this->renderPageLink($page);
        }
        
        $html .= $this->renderNextLink('&rsaquo;');
        
        $html .= '</ul></nav>';
        
        return $html;
    }

    /**
     * Individual link renderers
     */
    protected function renderPageLink(int $page): string
    {
        return sprintf(
            '<li class="page-item"><a href="%s" class="%s" aria-label="Page %d">%d</a></li>',
            $this->url($page),
            $this->options['link_class'],
            $page,
            $page
        );
    }

    protected function renderActiveLink(int $page): string
    {
        return sprintf(
            '<li class="page-item %s"><a href="%s" class="%s" aria-current="page">%d</a></li>',
            $this->options['active_class'],
            $this->url($page),
            $this->options['link_class'],
            $page
        );
    }

    protected function renderPrevLink(?string $text = null): string
    {
        $text = $text ?? $this->options['prev_text'];
        
        if (!$this->hasPreviousPage()) {
            return sprintf(
                '<li class="page-item %s"><span class="%s">%s</span></li>',
                $this->options['disabled_class'],
                $this->options['link_class'],
                $text
            );
        }
        
        return sprintf(
            '<li class="page-item"><a href="%s" class="%s" rel="prev" aria-label="Previous page">%s</a></li>',
            $this->url($this->previousPage()),
            $this->options['link_class'],
            $text
        );
    }

    protected function renderNextLink(?string $text = null): string
    {
        $text = $text ?? $this->options['next_text'];
        
        if (!$this->hasNextPage()) {
            return sprintf(
                '<li class="page-item %s"><span class="%s">%s</span></li>',
                $this->options['disabled_class'],
                $this->options['link_class'],
                $text
            );
        }
        
        return sprintf(
            '<li class="page-item"><a href="%s" class="%s" rel="next" aria-label="Next page">%s</a></li>',
            $this->url($this->nextPage()),
            $this->options['link_class'],
            $text
        );
    }

    protected function renderFirstLink(): string
    {
        if ($this->currentPage <= 2) {
            return '';
        }
        
        return sprintf(
            '<li class="page-item"><a href="%s" class="%s" aria-label="First page">%s</a></li>',
            $this->url(1),
            $this->options['link_class'],
            $this->options['first_text']
        );
    }

    protected function renderLastLink(): string
    {
        if ($this->currentPage >= $this->lastPage - 1) {
            return '';
        }
        
        return sprintf(
            '<li class="page-item"><a href="%s" class="%s" aria-label="Last page">%s</a></li>',
            $this->url($this->lastPage),
            $this->options['link_class'],
            $this->options['last_text']
        );
    }

    protected function renderEllipsis(): string
    {
        return sprintf(
            '<li class="page-item %s"><span class="%s">%s</span></li>',
            $this->options['ellipsis_class'],
            $this->options['link_class'],
            $this->options['ellipsis_text']
        );
    }

    /**
     * Get AJAX Load More JavaScript
     */
    public function getAjaxScript(): string
    {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.querySelector('.btn-load-more');
    if (!loadMoreBtn) return;
    
    loadMoreBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const url = this.dataset.url;
        const container = document.querySelector(this.dataset.container);
        const loader = document.querySelector('.loading-indicator');
        
        this.style.display = 'none';
        if (loader) loader.style.display = 'block';
        
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newItems = doc.querySelector(this.dataset.container).innerHTML;
                const newBtn = doc.querySelector('.btn-load-more');
                
                if (container) {
                    container.insertAdjacentHTML('beforeend', newItems);
                }
                
                if (newBtn) {
                    this.dataset.url = newBtn.dataset.url;
                    this.dataset.page = newBtn.dataset.page;
                    this.style.display = 'block';
                } else {
                    this.parentElement.innerHTML = '<div class="no-more-results">No more results</div>';
                }
                
                if (loader) loader.style.display = 'none';
            })
            .catch(error => {
                console.error('Error:', error);
                if (loader) loader.style.display = 'none';
                this.style.display = 'block';
            });
    });
});
</script>
JS;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to,
            'first_page_url' => $this->url(1),
            'last_page_url' => $this->url($this->lastPage),
            'next_page_url' => $this->hasNextPage() ? $this->url($this->nextPage()) : null,
            'prev_page_url' => $this->hasPreviousPage() ? $this->url($this->previousPage()) : null,
            'path' => $this->path,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __get(string $name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        }
        return null;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}