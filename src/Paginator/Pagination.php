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
 * // Render with a custom view template (e.g. for Tailwind or Bootstrap)
 * echo $users['paginator']->links('pagination.tailwind');
 *
 * // Standard render (Shades of Green)
 * echo $users['paginator']->render();
 *
 * // Load more style
 * echo $users['paginator']->renderLoadMore();
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
    protected $presenter;
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
        'item_class' => 'page-item',
        'link_class' => 'page-link',
        'active_class' => 'active',
        'disabled_class' => 'disabled',
        'info_class' => 'pagination-info',
        'ellipsis_class' => 'page-ellipsis',

        // Text/Icons (SVG Icons for modern look)
        'prev_text' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>',
        'next_text' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>',
        'first_text' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.354 1.646a.5.5 0 0 1 0 .708L2.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/><path fill-rule="evenodd" d="M12.354 1.646a.5.5 0 0 1 0 .708L6.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>',
        'last_text' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M3.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L9.293 8 3.646 2.354a.5.5 0 0 1 0-.708z"/><path fill-rule="evenodd" d="M7.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L13.293 8 7.646 2.354a.5.5 0 0 1 0-.708z"/></svg>',
        'ellipsis_text' => '...',
        'load_more_text' => 'Load More',
        'loading_text' => 'Loading...',

        // Styling options
        'theme' => 'green', // green, dark, minimalist
        'rounded' => true,
        'shadow' => true,
        'animated' => true,

        // Info format
        'info_format' => 'Showing <strong>{from}</strong> to <strong>{to}</strong> of <strong>{total}</strong> results',
        'info_format_single' => 'Showing <strong>{total}</strong> result',
        'info_format_empty' => 'No results found',

        // AJAX options
        'ajax_enabled' => false,
        'ajax_container' => '#results-container',
        'ajax_loader' => '.loader',

        // New Features
        'show_goto' => false,
        'goto_text' => 'Go to page',
    ];

    /**
     * Constructor
     */
    public function __construct(array|Collection $items, int $perPage = 15, int $currentPage = 1, ?int $total = null)
    {
        $this->items = $items instanceof Collection ? $items->all() : $items;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->total = $total ?? count($this->items);
        $this->lastPage = (int) ceil($this->total / $this->perPage);
        $this->currentPage = min($this->currentPage, max(1, $this->lastPage));

        $offset = ($this->currentPage - 1) * $this->perPage;

        if ($total === null) {
            $this->items = array_slice($this->items, $offset, $this->perPage);
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

        return new self($collection, $perPage, $currentPage, $total);
    }

    /**
     * Create paginator from array data (supports PlugModel pagination format)
     */
    public static function fromArray(array $paginationData): self
    {
        $data = $paginationData['data'] ?? [];

        // Handle meta structure from PlugModel
        if (isset($paginationData['meta'])) {
            $meta = $paginationData['meta'];
            $perPage = $meta['per_page'] ?? 15;
            $currentPage = $meta['current_page'] ?? 1;
            $total = $meta['total'] ?? (is_countable($data) ? count($data) : 0);
        } else {
            $perPage = $paginationData['per_page'] ?? 15;
            $currentPage = $paginationData['current_page'] ?? 1;
            $total = $paginationData['total'] ?? (is_countable($data) ? count($data) : 0);
        }

        return new self($data, $perPage, $currentPage, $total);
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
     * Append CSS classes to specific elements
     * 
     * @param array $classes Mapping of key to class string (e.g. ['container_class' => 'my-custom-class'])
     */
    public function addClasses(array $classes): self
    {
        foreach ($classes as $key => $class) {
            if (isset($this->options[$key])) {
                $this->options[$key] .= ' ' . $class;
            }
        }

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
     * Set a custom presenter for rendering
     */
    public function setPresenter(callable $presenter): self
    {
        $this->presenter = $presenter;
        return $this;
    }

    /**
     * Get items
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function from(): int
    {
        return $this->from;
    }

    public function to(): int
    {
        return $this->to;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function previousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    public function nextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    /**
     * Build URL for a page
     */
    public function url(int $page): string
    {
        $query = array_merge($this->query, ['page' => $page]);
        $queryString = http_build_query($query);

        return $this->path . ($queryString ? '?' . $queryString : '');
    }

    public function previousPageUrl(): ?string
    {
        return $this->hasPreviousPage() ? $this->url($this->previousPage()) : null;
    }

    public function nextPageUrl(): ?string
    {
        return $this->hasNextPage() ? $this->url($this->nextPage()) : null;
    }


    /**
     * Get page range with ellipsis support
     */
    protected function getPageRangeWithEllipsis(): array
    {
        $maxLinks = $this->options['max_links'];

        // If last page is small, we still want to separate first/last if they are handled manually
        // But for simplicity, we can just return the middle range

        $half = (int) floor($maxLinks / 2);
        $start = max(2, $this->currentPage - $half);
        $end = min($this->lastPage - 1, $start + $maxLinks - 1);

        // Adjust if we are near the end
        if ($this->lastPage > 1 && $end - $start < $maxLinks - 1) {
            $start = max(2, $end - $maxLinks + 1);
        }

        return [
            'pages' => $this->lastPage > 2 ? range($start, $end) : [],
            'show_first_ellipsis' => $start > 2,
            'show_last_ellipsis' => $end < $this->lastPage - 1,
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
        if (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'index.php') !== false) {
            $path = $_SERVER['REQUEST_URI'] ?? '/';
            return strtok($path, '?');
        }
        return strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    }

    /**
     * Get current query
     */
    protected function getCurrentQuery(): array
    {
        $query = $_GET;
        unset($query['page']);

        // Sanitize query to avoid XSS
        foreach ($query as $key => $value) {
            if (is_string($value)) {
                $query[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $query;
    }

    /**
     * Render info text
     */
    public function renderInfo(): string
    {
        if ($this->total === 0) {
            return sprintf(
                '<div class="%s">%s</div>',
                $this->options['info_class'],
                $this->options['info_format_empty']
            );
        }

        if ($this->total === 1) {
            $text = str_replace('{total}', (string) $this->total, $this->options['info_format_single']);
        } else {
            $text = str_replace(
                ['{from}', '{to}', '{total}'],
                [(string) $this->from, (string) $this->to, (string) $this->total],
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
        if (isset($this->presenter)) {
            return ($this->presenter)($this);
        }

        return $this->renderWithEllipsis();
    }

    /**
     * Render the pagination links using a view template or default renderer.
     *
     * @param string|null $view Template name
     * @param array $data Extra data for the view
     * @return string
     */
    public function links(?string $view = null, array $data = []): string
    {
        if ($view) {
            return view($view, array_merge([
                'paginator' => $this,
                'elements' => $this->elements()
            ], $data));
        }

        return $this->render();
    }

    /**
     * Get the elements for the pagination (useful for custom views)
     *
     * @return array
     */
    public function elements(): array
    {
        $range = $this->getPageRangeWithEllipsis();
        $elements = [];

        // First page
        $elements[] = [
            'type' => 'page',
            'page' => 1,
            'url' => $this->url(1),
            'is_active' => $this->currentPage === 1
        ];

        if ($range['show_first_ellipsis']) {
            $elements[] = ['type' => 'ellipsis'];
        }

        foreach ($range['pages'] as $page) {
            $elements[] = [
                'type' => 'page',
                'page' => $page,
                'url' => $this->url($page),
                'is_active' => $this->currentPage === $page
            ];
        }

        if ($range['show_last_ellipsis']) {
            $elements[] = ['type' => 'ellipsis'];
        }

        if ($this->lastPage > 1) {
            $elements[] = [
                'type' => 'page',
                'page' => $this->lastPage,
                'url' => $this->url($this->lastPage),
                'is_active' => $this->currentPage === $this->lastPage
            ];
        }

        return $elements;
    }

    /**
     * Render floating style pagination (modern centered look)
     */
    public function renderFloating(): string
    {
        if (!$this->hasPages()) {
            return $this->renderInfo();
        }

        $range = $this->getPageRangeWithEllipsis();

        $html = '<div class="' . $this->options['container_class'] . ' plugs-pagination-floating ' . $this->getThemeClasses() . '">';
        $html .= '<nav aria-label="Page navigation">';
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';

        // Previous
        $html .= $this->renderPrevLink();

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
        $html .= $this->renderNextLink();

        $html .= '</ul>';

        if ($this->options['show_goto']) {
            $html .= $this->renderGoto();
        }

        $html .= '</nav></div>';

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

        $html = '<div class="' . $this->options['container_class'] . ' ' . $this->getThemeClasses() . '">';
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

        $html .= '</ul>';

        if ($this->options['show_goto']) {
            $html .= $this->renderGoto();
        }

        $html .= '</nav></div>';

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
    protected function renderPageLink(int $page, ?string $text = null): string
    {
        $text = $text ?? (string) $page;
        return sprintf(
            '<li class="%s"><a href="%s" class="%s" aria-label="Page %d">%s</a></li>',
            $this->options['item_class'],
            $this->url($page),
            $this->options['link_class'],
            $page,
            $text
        );
    }

    protected function renderActiveLink(int $page): string
    {
        return sprintf(
            '<li class="%s %s"><a href="%s" class="%s" aria-current="page" aria-label="Current page, Page %d">%d</a></li>',
            $this->options['item_class'],
            $this->options['active_class'],
            $this->url($page),
            $this->options['link_class'],
            $page,
            $page
        );
    }

    protected function renderPrevLink(?string $text = null): string
    {
        $text = $text ?? $this->options['prev_text'];

        if (!$this->hasPreviousPage()) {
            return sprintf(
                '<li class="%s %s"><span class="%s">%s</span></li>',
                $this->options['item_class'],
                $this->options['disabled_class'],
                $this->options['link_class'],
                $text
            );
        }

        return sprintf(
            '<li class="%s"><a href="%s" class="%s" rel="prev" aria-label="Previous page">%s</a></li>',
            $this->options['item_class'],
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
                '<li class="%s %s"><span class="%s">%s</span></li>',
                $this->options['item_class'],
                $this->options['disabled_class'],
                $this->options['link_class'],
                $text
            );
        }

        return sprintf(
            '<li class="%s"><a href="%s" class="%s" rel="next" aria-label="Next page">%s</a></li>',
            $this->options['item_class'],
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

        return $this->renderPageLink(1, $this->options['first_text']);
    }

    protected function renderLastLink(): string
    {
        if ($this->currentPage >= $this->lastPage - 1) {
            return '';
        }

        return $this->renderPageLink($this->lastPage, $this->options['last_text']);
    }

    protected function renderEllipsis(): string
    {
        return sprintf(
            '<li class="%s %s"><span class="%s">%s</span></li>',
            $this->options['item_class'],
            $this->options['ellipsis_class'],
            $this->options['link_class'],
            $this->options['ellipsis_text']
        );
    }


    /**
     * Render JSON-LD metadata for search engines
     */
    public function renderJsonLd(): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'SearchResultsPage',
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => [],
            ],
            'pagination' => [
                '@type' => 'DataFeed',
                'totalItems' => $this->total,
                'itemsPerPage' => $this->perPage,
                'currentPage' => $this->currentPage,
                'totalPages' => $this->lastPage,
            ]
        ];

        // Add typical list items if data available
        $position = 1;
        foreach ($this->items as $item) {
            $data['mainEntity']['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => method_exists($item, 'url') ? $item->url() : null,
                'name' => $item->name ?? $item->title ?? null,
            ];
        }

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
    }

    /**
     * Render Go to Page input
     */
    protected function renderGoto(): string
    {
        return sprintf(
            '<div class="plugs-pagination-goto">
                <span>%s</span>
                <input type="number" min="1" max="%d" value="%d" onchange="window.location.href=\'%s\'.replace(\'PAGE_PLACEHOLDER\', this.value)">
            </div>',
            $this->options['goto_text'],
            $this->lastPage,
            $this->currentPage,
            str_replace('page=999999', 'page=PAGE_PLACEHOLDER', $this->url(999999))
        );
    }

    /**
     * Get theme classes
     */
    protected function getThemeClasses(): string
    {
        $classes = 'plugs-theme-' . $this->options['theme'];
        if ($this->options['rounded'])
            $classes .= ' plugs-rounded';
        if ($this->options['shadow'])
            $classes .= ' plugs-shadow';
        if ($this->options['animated'])
            $classes .= ' plugs-animated';
        return $classes;
    }

    /**
     * Get standard CSS for the pagination
     */
    public static function getStyles(): string
    {
        return <<<'CSS'
<style>
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.pagination-info {
    font-size: 0.875rem;
    color: #6b7280;
}

.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 0.25rem;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    height: 2.25rem;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

/* Green Theme */
.plugs-theme-green .page-link {
    color: #2d6a4f;
    background: #f0fdf4;
    border: 1px solid #dcfce7;
}

.plugs-theme-green .page-link:hover:not(.disabled) {
    background: #dcfce7;
    border-color: #bbf7d0;
    transform: translateY(-1px);
}

.plugs-theme-green .active .page-link {
    background: #2d6a4f;
    color: white;
    border-color: #2d6a4f;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.plugs-theme-green .disabled .page-link {
    color: #9ca3af;
    background: #f9fafb;
    border-color: #f3f4f6;
    cursor: not-allowed;
}

/* Floating Style */
.plugs-pagination-floating {
    justify-content: center;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(8px);
    border-radius: 1rem !important;
    padding: 0.75rem !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.plugs-pagination-floating .pagination-info {
    display: none;
}

/* Go to Page */
.plugs-pagination-goto {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: 1rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.plugs-pagination-goto input {
    width: 3.5rem;
    padding: 0.25rem 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    outline: none;
}

.plugs-pagination-goto input:focus {
    border-color: #2d6a4f;
    ring: 2px solid rgba(45, 106, 79, 0.2);
}

/* Animated */
.plugs-animated .page-link {
    transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), background-color 0.2s;
}

.plugs-animated .page-link:hover:not(.disabled) {
    transform: scale(1.1);
}

/* Rounded */
.plugs-rounded .page-link {
    border-radius: 9999px;
}

/* Shadow */
.plugs-shadow .pagination {
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.05));
}

/* SVG Icon alignment */
.page-link svg {
    display: block;
}
</style>
CSS;
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
                const newItemsContainer = doc.querySelector(this.dataset.container);
                if (!newItemsContainer) {
                    console.error('AJAX Container not found in response');
                    return;
                }
                const newItems = newItemsContainer.innerHTML;
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
