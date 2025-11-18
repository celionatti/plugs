<?php

declare(strict_types=1);

namespace Plugs\Paginator;

/**
 * Paginator Class
 * 
 * A flexible pagination class that can be used with any type of data.
 * Supports both array data and query builder results.
 * 
 * @example
 * // With array data
 * $data = range(1, 100);
 * $paginator = new Paginator($data, 10, 1);
 * 
 * // With query builder
 * $query = User::where('active', 1);
 * $paginator = Paginator::fromQuery($query, 15, 2);
 * 
 * // Render in view
 * echo $paginator->render();
 */
class Paginator
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
        'show_numbers' => true,
        'show_first_last' => true,
        'show_prev_next' => true,
        'max_links' => 7, // Maximum number of page links to show
        'container_class' => 'table-footer',
        'pagination_class' => 'pagination',
        'link_class' => '',
        'active_class' => 'active',
        'disabled_class' => 'disabled',
        'info_class' => 'showing-info',
        'prev_text' => '<i class="bi bi-chevron-left"></i> Previous',
        'next_text' => 'Next <i class="bi bi-chevron-right"></i>',
        'first_text' => 'First',
        'last_text' => 'Last',
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

        // Calculate offset
        $offset = ($this->currentPage - 1) * $this->perPage;

        // If total is provided (from query), items are already sliced
        if ($total !== null) {
            $this->items = $items;
        } else {
            // Slice items for current page (for array data)
            $this->items = array_slice($items, $offset, $this->perPage);
        }

        // Calculate from/to
        $this->from = $this->total > 0 ? $offset + 1 : 0;
        $this->to = min($offset + count($this->items), $this->total);

        // Set default path
        $this->path = $this->getCurrentPath();
        $this->query = $this->getCurrentQuery();
    }

    /**
     * Create paginator from query builder
     */
    public static function fromQuery($query, int $perPage = 15, int $currentPage = 1): self
    {
        // Get total count
        $total = $query->count();

        // Calculate offset
        $offset = ($currentPage - 1) * $perPage;

        // Get items for current page (keep as Collection/Model objects)
        $collection = $query->offset($offset)->limit($perPage)->get();
        
        // Convert Collection to array but keep model objects
        $items = [];
        foreach ($collection as $item) {
            $items[] = $item; // Keep as model object
        }

        $instance = new self($items, $perPage, $currentPage, $total);
        
        return $instance;
    }

    /**
     * Create simple paginator (no total count)
     */
    public static function simple(array $items, int $perPage = 15, int $currentPage = 1): self
    {
        $instance = new self($items, $perPage + 1, $currentPage); // Get one extra to check if there's more
        $instance->items = array_slice($instance->items, 0, $perPage); // Remove the extra
        
        return $instance;
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
     * Set base path for pagination links
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Add query parameters to pagination links
     */
    public function appends(array $query): self
    {
        $this->query = array_merge($this->query, $query);
        return $this;
    }

    /**
     * Get items for current page
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Get total items count
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get items per page
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get current page number
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get last page number
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get first item number
     */
    public function from(): int
    {
        return $this->from;
    }

    /**
     * Get last item number
     */
    public function to(): int
    {
        return $this->to;
    }

    /**
     * Check if there are more pages
     */
    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    /**
     * Check if on first page
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    /**
     * Check if on last page
     */
    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Get previous page number
     */
    public function previousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * Get next page number
     */
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

    /**
     * Get array of page numbers to display
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

        // Adjust start if end is at last page
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
     * Get current query parameters
     */
    protected function getCurrentQuery(): array
    {
        $query = $_GET ?? [];
        unset($query['page']); // Remove page parameter
        return $query;
    }

    /**
     * Render pagination HTML
     */
    public function render(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<div class="' . $this->options['container_class'] . '">';
        
        // Showing info
        $html .= $this->renderInfo();
        
        // Pagination links
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';
        
        // First page link
        if ($this->options['show_first_last'] && $this->currentPage > 2) {
            $html .= $this->renderLink(1, $this->options['first_text']);
            if ($this->currentPage > 3) {
                $html .= '<li><span>...</span></li>';
            }
        }
        
        // Previous page link
        if ($this->options['show_prev_next'] && $this->hasPreviousPage()) {
            $html .= $this->renderLink($this->previousPage(), $this->options['prev_text']);
        }
        
        // Page numbers
        if ($this->options['show_numbers']) {
            foreach ($this->getPageRange() as $page) {
                if ($page === $this->currentPage) {
                    $html .= $this->renderActiveLink($page);
                } else {
                    $html .= $this->renderLink($page, (string) $page);
                }
            }
        }
        
        // Next page link
        if ($this->options['show_prev_next'] && $this->hasNextPage()) {
            $html .= $this->renderLink($this->nextPage(), $this->options['next_text']);
        }
        
        // Last page link
        if ($this->options['show_first_last'] && $this->currentPage < $this->lastPage - 1) {
            if ($this->currentPage < $this->lastPage - 2) {
                $html .= '<li><span>...</span></li>';
            }
            $html .= $this->renderLink($this->lastPage, $this->options['last_text']);
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render showing info
     */
    protected function renderInfo(): string
    {
        if ($this->total === 0) {
            return '<div class="' . $this->options['info_class'] . '">No items found</div>';
        }

        return sprintf(
            '<div class="%s">Showing %d to %d of %d items</div>',
            $this->options['info_class'],
            $this->from,
            $this->to,
            $this->total
        );
    }

    /**
     * Render a pagination link
     */
    protected function renderLink(?int $page, string $text): string
    {
        if ($page === null) {
            return sprintf(
                '<li><a class="%s %s">%s</a></li>',
                $this->options['link_class'],
                $this->options['disabled_class'],
                $text
            );
        }

        return sprintf(
            '<li><a href="%s" class="%s">%s</a></li>',
            $this->url($page),
            $this->options['link_class'],
            $text
        );
    }

    /**
     * Render active page link
     */
    protected function renderActiveLink(int $page): string
    {
        return sprintf(
            '<li><a href="%s" class="%s %s">%d</a></li>',
            $this->url($page),
            $this->options['link_class'],
            $this->options['active_class'],
            $page
        );
    }

    /**
     * Render simple pagination (prev/next only)
     */
    public function renderSimple(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<div class="' . $this->options['container_class'] . '">';
        $html .= '<ul class="' . $this->options['pagination_class'] . '">';
        
        // Previous
        if ($this->hasPreviousPage()) {
            $html .= $this->renderLink($this->previousPage(), $this->options['prev_text']);
        } else {
            $html .= $this->renderLink(null, $this->options['prev_text']);
        }
        
        // Current page indicator
        $html .= sprintf('<li><span>Page %d of %d</span></li>', $this->currentPage, $this->lastPage);
        
        // Next
        if ($this->hasNextPage()) {
            $html .= $this->renderLink($this->nextPage(), $this->options['next_text']);
        } else {
            $html .= $this->renderLink(null, $this->options['next_text']);
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
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
            'total_pages' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to,
            'first_page_url' => $this->url(1),
            'last_page_url' => $this->url($this->lastPage),
            'next_page_url' => $this->hasNextPage() ? $this->url($this->nextPage()) : null,
            'prev_page_url' => $this->hasPreviousPage() ? $this->url($this->previousPage()) : null,
            'path' => $this->path,
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic method to access properties
     */
    public function __get(string $name)
    {
        $method = $name;
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }

    /**
     * Magic method for string conversion
     */
    public function __toString(): string
    {
        return $this->render();
    }
}