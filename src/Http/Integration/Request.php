<?php

declare(strict_types=1);

namespace Plugs\Http\Integration;

use Plugs\Http\Integration\Enums\Method;

abstract class Request
{
    /**
     * The HTTP method for the request.
     */
    protected string $method = Method::GET;

    /**
     * Resolve the endpoint for the request.
     */
    abstract public function resolveEndpoint(): string;

    /**
     * Get the HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the headers for the request.
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * Get the query parameters for the request.
     */
    public function query(): array
    {
        return [];
    }

    /**
     * Get the body of the request.
     */
    public function body(): mixed
    {
        return [];
    }

    /**
     * Check if the request has specific configuration.
     */
    public function config(): array
    {
        return [];
    }
}
