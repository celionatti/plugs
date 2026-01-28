<?php

declare(strict_types=1);

namespace Plugs\Http\Resources;

use JsonSerializable;
use Plugs\Base\Model\PlugModel;
use Plugs\Database\Collection;
use Plugs\Http\StandardResponse;
use Plugs\Paginator\Paginator;

/**
 * PlugResourceCollection
 * 
 * Base class for API resource collections that transform multiple models/items.
 * Supports pagination, meta data, and links.
 * 
 * @package Plugs\Http\Resources
 */
class PlugResourceCollection implements JsonSerializable
{
    /**
     * The collection of resources
     */
    public Collection|array $collection;

    /**
     * The resource class to use for each item
     */
    protected string $collects;

    /**
     * Meta data to include in response
     */
    protected array $meta = [];

    /**
     * Links to include in response
     */
    protected array $links = [];

    /**
     * Additional data to append to response
     */
    protected array $additional = [];

    /**
     * Pagination data
     */
    protected ?array $pagination = null;

    /**
     * The wrapper key for the collection data
     */
    public static ?string $wrap = 'data';

    /**
     * Response callback for customizing the HTTP response
     */
    protected ?\Closure $responseCallback = null;

    /**
     * Create a new resource collection
     */
    public function __construct(mixed $resource, ?string $collects = null)
    {
        if ($resource instanceof Paginator) {
            $this->collection = $resource->items() instanceof Collection ? $resource->items() : new Collection($resource->items());

            $this->withPagination(
                $resource->total(),
                $resource->perPage(),
                $resource->currentPage(),
                null // Path is handled by Paginator's url() method
            );

            // Copy query parameters if any (appends)
            // Note: Paginator::appends() sets options on inner Pagination object
            // We might need to manually sync links if appends were used, but withPagination regenerates them.
            // However, Paginator already handles link generation with parameters. 
            // Instead of regenerating links via withPagination's simple logic, 
            // we should trust the Paginator's own link generation if possible, 
            // OR pass the query params to withPagination if we want to stick to our simple generation.

            // Better approach: Use the data from Paginator directly
            // But withPagination sets $this->pagination array structure which toResponse() relies on.
            // So we call withPagination to set up the structure.

            // If the Paginator has appended query params, we might want to ensure they are preserved in the path
            // or we overwrite $this->links with Paginator's links.
            // Overwrite links with Paginator's generated links (preserves query params)
            $this->links = [
                'first' => $resource->url(1),
                'last' => $resource->url($resource->lastPage()),
                'prev' => $resource->previousPage() ? $resource->url($resource->previousPage()) : null,
                'next' => $resource->nextPage() ? $resource->url($resource->nextPage()) : null,
            ];

        } else {
            $this->collection = $resource instanceof Collection ? $resource : new Collection($resource);
        }

        $this->collects = $collects ?? $this->detectCollects();
    }

    /**
     * Detect what resource class to use based on class name
     */
    protected function detectCollects(): string
    {
        // Try to find corresponding Resource class (e.g., UserCollection -> UserResource)
        $className = static::class;
        $resourceClass = str_replace('Collection', 'Resource', $className);

        if (class_exists($resourceClass)) {
            return $resourceClass;
        }

        // Default to anonymous resource
        return PlugResource::class;
    }

    /**
     * Static factory method
     */
    public static function make(Collection|array $resource): static
    {
        return new static($resource);
    }

    /**
     * Transform the collection into an array
     */
    public function toArray(): array
    {
        $items = $this->collection instanceof Collection
            ? $this->collection->all()
            : $this->collection;

        return array_map(function ($item) {
            if ($this->collects && class_exists($this->collects)) {
                $resource = new $this->collects($item);
                return $resource instanceof PlugResource ? $resource->resolve() : $resource->toArray();
            }

            if ($item instanceof PlugModel) {
                return $item->toArray();
            }

            return is_array($item) ? $item : (array) $item;
        }, $items);
    }

    /**
     * Add meta data to the collection
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Add links to the collection
     */
    public function withLinks(array $links): static
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    /**
     * Add pagination data
     */
    public function withPagination(int $total, int $perPage, int $currentPage, ?string $path = null): static
    {
        $lastPage = (int) ceil($total / $perPage);
        $from = ($currentPage - 1) * $perPage + 1;
        $to = min($currentPage * $perPage, $total);

        $this->pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $from : null,
            'to' => $total > 0 ? $to : null,
        ];

        // Auto-generate pagination links
        if ($path) {
            $this->links = [
                'first' => "{$path}?page=1",
                'last' => "{$path}?page={$lastPage}",
                'prev' => $currentPage > 1 ? "{$path}?page=" . ($currentPage - 1) : null,
                'next' => $currentPage < $lastPage ? "{$path}?page=" . ($currentPage + 1) : null,
            ];
        }

        return $this;
    }

    /**
     * Add additional data to the response
     */
    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    /**
     * Set response callback for customizing the HTTP response
     */
    public function withResponse(\Closure $callback): static
    {
        $this->responseCallback = $callback;
        return $this;
    }

    /**
     * Convert to StandardResponse
     */
    public function toResponse(int $status = 200, ?string $message = 'Success'): StandardResponse
    {
        $responseData = $this->toArray();

        // Create the response
        $response = new StandardResponse($responseData, true, $status, $message);

        // Add meta data (including pagination)
        $meta = $this->meta;
        if ($this->pagination) {
            $meta['pagination'] = $this->pagination;
        }
        if (!empty($meta)) {
            $response->withMeta($meta);
        }

        // Add links
        if (!empty($this->links)) {
            $response->withLinks($this->links);
        }

        // Apply response callback if set
        if ($this->responseCallback) {
            ($this->responseCallback)($response);
        }

        return $response;
    }

    /**
     * Get count of items
     */
    public function count(): int
    {
        return $this->collection instanceof Collection
            ? $this->collection->count()
            : count($this->collection);
    }

    /**
     * Convert to JSON string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * JsonSerializable implementation
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convert to string (JSON)
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Disable the wrapper for this collection
     */
    public static function withoutWrapping(): void
    {
        static::$wrap = null;
    }

    /**
     * Set a custom wrapper key
     */
    public static function wrap(string $key): void
    {
        static::$wrap = $key;
    }
}
