<?php

declare(strict_types=1);

if (defined('PLUGS_DUMP_LOADED'))
    return;
define('PLUGS_DUMP_LOADED', true);

use Plugs\Debug\Dumper;

/*
|--------------------------------------------------------------------------
| Plugs Framework Debug Utility - Refactored
|--------------------------------------------------------------------------
|
| Thin wrappers around the Plugs\Debug\Dumper service.
*/

if (!function_exists('dd')) {
    /**
     * Plugs Debug & Die - Dump and terminate execution
     */
    function dd(...$vars): void
    {
        plugs_dump($vars, true);
    }
}

if (!function_exists('d')) {
    /**
     * Plugs Dump - Dump without dying
     */
    function d(...$vars): void
    {
        plugs_dump($vars, false);
    }
}

if (!function_exists('dq')) {
    /**
     * Dump Queries - Show all executed queries
     */
    function dq(bool $die = true): void
    {
        $modelClass = 'Plugs\\Base\\Model\\PlugModel';
        if (!class_exists($modelClass)) {
            (new Dumper())->renderFallbackError('Model class not found. Make sure PlugModel is loaded.', $die);
            return;
        }

        try {
            /** @var mixed $modelClass */
            $queries = $modelClass::getQueryLog();
            $totalTime = array_sum(array_column($queries, 'time'));

            $data = [
                'queries' => $queries,
                'stats' => [
                    'total_queries' => count($queries),
                    'total_time' => $totalTime,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true),
                ],
            ];

            plugs_dump([$data], $die, 'query');
        } catch (\Exception $e) {
            (new Dumper())->renderFallbackError($e->getMessage(), $die);
        }
    }
}

if (!function_exists('dm')) {
    /**
     * Dump Model - Show model with relations and queries
     */
    function dm($model, bool $die = true): void
    {
        if (!is_object($model)) {
            plugs_dump([$model], $die);
            return;
        }

        $data = [
            'model' => $model,
            'queries' => method_exists($model, 'getQueryLog') ? $model::getQueryLog() : [],
        ];

        plugs_dump([$data], $die, 'model');
    }
}

if (!function_exists('de')) {
    /**
     * Dump Exception - Show exception with beautiful stack trace
     */
    function de(Throwable $exception, bool $die = true): void
    {
        $data = [
            'exception' => $exception,
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
            'previous' => $exception->getPrevious(),
        ];

        plugs_dump([$data], $die, 'exception');
    }
}

if (!function_exists('dh')) {
    /**
     * Dump HTTP - Show HTTP response with headers, body, and timing
     */
    function dh($response, bool $die = true): void
    {
        $data = [];
        if (is_object($response)) {
            if (method_exists($response, 'getStatusCode'))
                $data['status_code'] = $response->getStatusCode();
            if (method_exists($response, 'getHeaders'))
                $data['headers'] = $response->getHeaders();
            if (method_exists($response, 'getBody')) {
                $body = $response->getBody();
                $data['body'] = $body;
                if (is_string($body)) {
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE)
                        $data['body_parsed'] = $decoded;
                }
            }
            if (method_exists($response, 'getReasonPhrase'))
                $data['reason'] = $response->getReasonPhrase();
            if (method_exists($response, 'getRequestTime'))
                $data['request_time'] = $response->getRequestTime();
            if (method_exists($response, 'getUrl'))
                $data['url'] = $response->getUrl();
            $data['response_class'] = get_class($response);
        } else {
            $data = ['response' => $response];
        }

        plugs_dump([$data], $die, 'http');
    }
}

if (!function_exists('dt')) {
    /**
     * Set Debug Theme
     */
    function dt(string $theme): void
    {
        Dumper::setTheme($theme);
    }
}

if (!function_exists('plugs_dump')) {
    /**
     * Core dump function - proxies to Dumper service
     */
    function plugs_dump(array $vars, bool $die = false, string $mode = 'default', ?string $nonce = null): void
    {
        (new Dumper())->dump($vars, $die, $mode, $nonce);
    }
}

if (!function_exists('plugs_get_query_stats')) {
    /**
     * Backward compatibility helpers
     */
    function plugs_get_query_stats(): array
    {
        $modelClass = 'Plugs\\Base\\Model\\PlugModel';
        if (!class_exists($modelClass))
            return [];

        try {
            /** @var mixed $modelClass */
            $queries = $modelClass::getQueryLog();
            return [
                'count' => count($queries),
                'time' => array_sum(array_column($queries, 'time')),
                'queries' => $queries,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}

if (!function_exists('plugs_render_styles')) {
    /**
     * Render Dumper CSS styles (global wrapper for ProfilerBar compatibility).
     */
    function plugs_render_styles(bool $scoped = false, ?string $nonce = null): string
    {
        /** @var Dumper $dumper */
        $dumper = new Dumper();
        return $dumper->renderStyles($scoped, $nonce);
    }
}

if (!function_exists('plugs_render_profile')) {
    /**
     * Render profile section (global wrapper for ProfilerBar compatibility).
     */
    function plugs_render_profile(array $data): string
    {
        /** @var Dumper $dumper */
        $dumper = new Dumper();
        return $dumper->renderProfile($data);
    }
}

if (!function_exists('plugs_render_queries')) {
    /**
     * Render queries section (global wrapper for ProfilerBar compatibility).
     */
    function plugs_render_queries(array $data): string
    {
        /** @var Dumper $dumper */
        $dumper = new Dumper();
        return $dumper->renderQueries($data);
    }
}

