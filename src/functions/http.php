<?php

declare(strict_types=1);

use Plugs\Http\Batch;
use Plugs\Http\HTTPClient;
use Plugs\Http\HTTPResponse;
use Plugs\Http\BatchResponse;

if (!function_exists('http')) {
    function http(?string $baseUri = null): HTTPClient
    {
        $http = HTTPClient::make();

        if ($baseUri !== null) {
            $http->baseUri($baseUri);
        }

        return $http;
    }
}

if (!function_exists('http_get')) {
    function http_get(string $url, array $query = []): HTTPResponse
    {
        return HTTPClient::make()->get($url, $query);
    }
}

if (!function_exists('http_post')) {
    function http_post(string $url, array $data = []): HTTPResponse
    {
        return HTTPClient::make()->post($url, $data);
    }
}

if (!function_exists('http_put')) {
    function http_put(string $url, array $data = []): HTTPResponse
    {
        return HTTPClient::make()->put($url, $data);
    }
}

if (!function_exists('http_patch')) {
    function http_patch(string $url, array $data = []): HTTPResponse
    {
        return HTTPClient::make()->patch($url, $data);
    }
}

if (!function_exists('http_delete')) {
    function http_delete(string $url, array $data = []): HTTPResponse
    {
        return HTTPClient::make()->delete($url, $data);
    }
}

if (!function_exists('batch')) {
    function batch(): Batch
    {
        return Batch::make();
    }
}

if (!function_exists('http_async')) {
    function http_async(array $requests): BatchResponse
    {
        $batch = Batch::make();

        foreach ($requests as $key => $request) {
            $method = strtolower($request['method'] ?? 'get');
            $url = $request['url'];
            $data = $request['data'] ?? [];
            $options = $request['options'] ?? [];
            $requestKey = is_string($key) ? $key : null;

            switch ($method) {
                case 'get':
                    $batch->get($url, $options, $requestKey);
                    break;
                case 'post':
                    $batch->post($url, $data, $options, $requestKey);
                    break;
                case 'put':
                    $batch->put($url, $data, $options, $requestKey);
                    break;
                case 'patch':
                    $batch->patch($url, $data, $options, $requestKey);
                    break;
                case 'delete':
                    $batch->delete($url, $options, $requestKey);
                    break;
                default:
                    $batch->add($method, $url, $options, $requestKey);
            }
        }

        return $batch->send();
    }
}

if (!function_exists('browser')) {
    function browser(): \Plugs\Http\Browser
    {
        return \Plugs\Http\Browser::make();
    }
}