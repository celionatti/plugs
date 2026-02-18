<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/**
 * MissingRouteParameterException
 * 
 * Thrown when a named route is requested but missing required parameters.
 */
class MissingRouteParameterException extends PlugsException
{
    /**
     * HTTP status code for server errors (parameter mismatch is a developer error).
     *
     * @var int
     */
    protected int $statusCode = 500;

    /**
     * The name of the route.
     *
     * @var string
     */
    protected string $routeName;

    /**
     * The route path pattern.
     *
     * @var string
     */
    protected string $path;

    /**
     * List of missing parameter names.
     *
     * @var array
     */
    protected array $missingParameters;

    /**
     * Create a new exception instance.
     *
     * @param string $routeName
     * @param string $path
     * @param array $missingParameters
     * @param array $providedParameters
     */
    public function __construct(string $routeName, string $path, array $missingParameters, array $providedParameters = [])
    {
        $this->routeName = $routeName;
        $this->path = $path;
        $this->missingParameters = $missingParameters;

        $paramsStr = implode(', ', $missingParameters);
        $message = "Missing required parameters [{$paramsStr}] for route [{$routeName}] (Pattern: {$path}).";

        parent::__construct($message, 0, null, [
            'route_name' => $routeName,
            'path' => $path,
            'missing' => $missingParameters,
            'provided' => $providedParameters
        ]);
    }

    /**
     * Get the route name.
     *
     * @return string
     */
    public function getRouteName(): string
    {
        return $this->routeName;
    }

    /**
     * Get the route path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the missing parameters.
     *
     * @return array
     */
    public function getMissingParameters(): array
    {
        return $this->missingParameters;
    }
}
