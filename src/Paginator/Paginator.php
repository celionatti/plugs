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
    protected Pagination $pagination;

    /**
     * Constructor
     */
    public function __construct(array $items, int|string $perPage = 15, int|string $currentPage = 1, int|string|null $total = null)
    {
        $this->pagination = new Pagination($items, $perPage, $currentPage, $total);
    }

    /**
     * Create paginator from query builder
     */
    public static function fromQuery($query, int|string $perPage = 15, int|string $currentPage = 1): self
    {
        $pagination = Pagination::fromQuery($query, $perPage, $currentPage);
        $instance = new self([], $perPage, $currentPage, $pagination->total());
        $instance->pagination = $pagination;
        return $instance;
    }

    /**
     * Create simple paginator (no total count)
     */
    public static function simple(array $items, int|string $perPage = 15, int|string $currentPage = 1): self
    {
        $instance = new self($items, $perPage, $currentPage);
        return $instance;
    }

    /**
     * Set pagination options
     */
    public function setOptions(array $options): self
    {
        $this->pagination->setOptions($options);
        return $this;
    }

    /**
     * Set base path for pagination links
     */
    public function setPath(string $path): self
    {
        $this->pagination->setPath($path);
        return $this;
    }

    /**
     * Add query parameters to pagination links
     */
    public function appends(array $query): self
    {
        $this->pagination->appends($query);
        return $this;
    }

    /**
     * Get items for current page
     */
    public function items(): array
    {
        return $this->pagination->items();
    }

    /**
     * Get total items count
     */
    public function total(): int
    {
        return $this->pagination->total();
    }

    /**
     * Get items per page
     */
    public function perPage(): int
    {
        return $this->pagination->perPage();
    }

    /**
     * Get current page number
     */
    public function currentPage(): int
    {
        return $this->pagination->currentPage();
    }

    /**
     * Get last page number
     */
    public function lastPage(): int
    {
        return $this->pagination->lastPage();
    }

    /**
     * Get first item number
     */
    public function from(): int
    {
        return $this->pagination->from();
    }

    /**
     * Get last item number
     */
    public function to(): int
    {
        return $this->pagination->to();
    }

    /**
     * Check if there are more pages
     */
    public function hasPages(): bool
    {
        return $this->pagination->hasPages();
    }

    /**
     * Check if on first page
     */
    public function onFirstPage(): bool
    {
        return $this->pagination->onFirstPage();
    }

    /**
     * Check if on last page
     */
    public function onLastPage(): bool
    {
        return $this->pagination->onLastPage();
    }

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->pagination->hasPreviousPage();
    }

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool
    {
        return $this->pagination->hasNextPage();
    }

    /**
     * Get previous page number
     */
    public function previousPage(): ?int
    {
        return $this->pagination->previousPage();
    }

    /**
     * Get next page number
     */
    public function nextPage(): ?int
    {
        return $this->pagination->nextPage();
    }

    /**
     * Build URL for a page
     */
    public function url(int $page): string
    {
        return $this->pagination->url($page);
    }

    /**
     * Render pagination HTML
     */
    public function render(): string
    {
        return $this->pagination->render();
    }

    /**
     * Render info text
     */
    public function renderInfo(): string
    {
        return $this->pagination->renderInfo();
    }

    /**
     * Render simple pagination (prev/next only)
     */
    public function renderSimple(): string
    {
        return $this->pagination->renderSimple();
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->pagination->toArray();
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return $this->pagination->toJson();
    }

    /**
     * Magic method to access properties
     */
    public function __get(string $name)
    {
        return $this->pagination->$name;
    }

    /**
     * Magic method for string conversion
     */
    public function __toString(): string
    {
        return (string) $this->pagination;
    }
}

