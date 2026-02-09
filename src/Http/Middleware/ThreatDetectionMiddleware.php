<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| ThreatDetectionMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware automatically scans all requests for suspicious patterns
| using the ThreatDetector service. Blocks or logs threats based on config.
*/

use Plugs\Security\ThreatDetector;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ThreatDetectionMiddleware implements MiddlewareInterface
{
    private bool $blockSuspicious;
    private int $blockThreshold;

    public function __construct(bool $blockSuspicious = true, int $blockThreshold = 10)
    {
        $this->blockSuspicious = $blockSuspicious;
        $this->blockThreshold = $blockThreshold;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $threatScore = ThreatDetector::analyze($request);

        // Log all threats (even below threshold)
        if ($threatScore > 0) {
            $this->logThreat($request, $threatScore);
        }

        // Block if above threshold and blocking is enabled
        if ($this->blockSuspicious && $threatScore >= $this->blockThreshold) {
            return $this->blockRequest($request, $threatScore);
        }

        // Inject threat score into request for downstream use
        $request = $request->withAttribute('_threat_score', $threatScore);

        return $handler->handle($request);
    }

    private function logThreat(ServerRequestInterface $request, int $score): void
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Use framework logger if available
        if (function_exists('logger')) {
            logger()->warning("Threat detected", [
                'ip' => $ip,
                'method' => $method,
                'uri' => $uri,
                'score' => $score,
            ]);
        } else {
            error_log("[THREAT] Score: {$score} | IP: {$ip} | {$method} {$uri}");
        }
    }

    private function blockRequest(ServerRequestInterface $request, int $score): ResponseInterface
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

        // Log the block
        if (function_exists('logger')) {
            logger()->error("Request blocked due to high threat score", [
                'ip' => $ip,
                'score' => $score,
            ]);
        }

        return ResponseFactory::json([
            'error' => 'Forbidden',
            'message' => 'Your request has been blocked due to suspicious activity.',
        ], 403);
    }
}
