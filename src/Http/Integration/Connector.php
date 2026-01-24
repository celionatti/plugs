<?php

declare(strict_types=1);

namespace Plugs\Http\Integration;

use Plugs\Http\HTTPClient;
use Plugs\Http\HTTPResponse;
use Plugs\Http\Integration\Enums\Method;
use GuzzleHttp\Promise\PromiseInterface;

abstract class Connector
{
    /**
     * The underlying HTTP client instance.
     */
    protected ?HTTPClient $client = null;

    /**
     * Resolve the base URL for the connector.
     */
    abstract public function resolveBaseUrl(): string;

    /**
     * Get the default headers for all requests.
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * Get the default configuration for the connector.
     */
    public function defaultConfig(): array
    {
        return [];
    }

    /**
     * Get or create the HTTP client instance.
     */
    public function client(): HTTPClient
    {
        if (!$this->client) {
            $this->client = new HTTPClient();
        }

        return $this->client;
    }

    /**
     * Prepare the HTTP client with connector and request settings.
     */
    protected function prepareClient(Request $request): HTTPClient
    {
        $client = $this->client()->reset();

        // Base URL
        $client->baseUri($this->resolveBaseUrl());

        // Headers
        $headers = array_merge(
            $this->headers(),
            $request->headers()
        );

        // Trait usage for headers (e.g. HasJsonBody)
        if (method_exists($request, 'defaultHeaders')) {
            $headers = array_merge($headers, $request->defaultHeaders());
        }

        $client->headers($headers);

        // Query Parameters
        $client->query($request->query());

        // Body
        $body = $request->body();
        if (!empty($body)) {
            // Basic detection of body type based on Content-Type header or traits could be better,
            // but for now relying on usage.
            // If HasJsonBody is used, we usually want to send as JSON.
            // If HasFormParams is used, we want to send as form_params.

            // Check for Content-Type to decide how to send
            $contentType = $headers['Content-Type'] ?? null;

            if ($contentType === 'application/json') {
                $client->json($body);
            } elseif ($contentType === 'application/x-www-form-urlencoded') {
                $client->form($body);
            } else {
                // Fallback: if array, assume JSON or form based on method? 
                // Actually existing HTTPClient separates json() and form().
                // Let's assume JSON by default if body is array and method is not GET?
                if (is_array($body)) {
                    $client->json($body);
                }
            }
        }

        // Configs (Timeout, Retries, etc)
        $config = array_merge($this->defaultConfig(), $request->config());
        if (isset($config['timeout'])) {
            $client->timeout($config['timeout']);
        }

        // TODO: Map other config options

        return $client;
    }

    /**
     * Send a request synchronously.
     */
    public function send(Request $request): HTTPResponse
    {
        $client = $this->prepareClient($request);
        $method = $request->method();
        $url = $request->resolveEndpoint();

        switch ($method) {
            case Method::GET:
                return $client->get($url);
            case Method::POST:
                return $client->post($url);
            case Method::PUT:
                return $client->put($url);
            case Method::PATCH:
                return $client->patch($url);
            case Method::DELETE:
                return $client->delete($url);
            default:
                // Fallback for other methods if supported by HTTPClient, or throw
                throw new \InvalidArgumentException("Method {$method} not supported yet in Connector.");
        }
    }

    /**
     * Send a request asynchronously.
     */
    public function sendAsync(Request $request): PromiseInterface
    {
        $client = $this->prepareClient($request);
        $method = $request->method();
        $url = $request->resolveEndpoint();

        // HTTPClient has getAsync, postAsync. 
        // We might need to extend HTTPClient to support generic requestAsync if we want full coverage.
        // For now:
        if ($method === Method::GET) {
            return $client->getAsync($url);
        } elseif ($method === Method::POST) {
            return $client->postAsync($url);
        }

        // Fallback or todo
        throw new \InvalidArgumentException("Async method {$method} not supported yet in Connector.");
    }
}
