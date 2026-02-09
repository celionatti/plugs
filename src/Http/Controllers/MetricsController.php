<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\ResponseFactory;
use Plugs\Metrics\MetricsCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Metrics Controller.
 * Exposes metrics for Prometheus and other monitoring tools.
 */
class MetricsController
{
    /**
     * Prometheus-compatible metrics endpoint.
     */
    public function prometheus(ServerRequestInterface $request): ResponseInterface
    {
        $metrics = MetricsCollector::prometheus();

        return ResponseFactory::create($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /**
     * JSON metrics endpoint.
     */
    public function json(ServerRequestInterface $request): ResponseInterface
    {
        return ResponseFactory::json([
            'timestamp' => date('c'),
            'metrics' => MetricsCollector::json(),
        ]);
    }
}
