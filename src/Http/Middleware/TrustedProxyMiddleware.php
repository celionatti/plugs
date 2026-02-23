<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| TrustedProxyMiddleware
|--------------------------------------------------------------------------
|
| When your application runs behind a reverse proxy, load balancer, or CDN
| (e.g. Nginx, Cloudflare, AWS ALB), the client's real IP and protocol are
| forwarded via headers like X-Forwarded-For and X-Forwarded-Proto.
|
| This middleware rewrites the request object so that $request->getUri()
| reflects the correct scheme and $request->getClientIp() returns the
| real client IP instead of the proxy's IP.
|
| Usage in config/middleware.php:
|   'trusted_proxy' => \Plugs\Http\Middleware\TrustedProxyMiddleware::class,
|
| Configuration via constructor or config('security.trusted_proxies'):
|   'trusted_proxies' => ['192.168.1.0/24', '10.0.0.1'],
|   // Or use '*' to trust all proxies (use with caution)
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrustedProxyMiddleware implements MiddlewareInterface
{
    /** @var array List of trusted proxy IPs or CIDR ranges */
    private array $trustedProxies;

    /** @var array Headers to trust for forwarded values */
    private array $trustedHeaders;

    public function __construct(?array $trustedProxies = null, ?array $trustedHeaders = null)
    {
        $this->trustedProxies = $trustedProxies
            ?? config('security.trusted_proxies', []);

        $this->trustedHeaders = $trustedHeaders ?? [
            'forwarded_for' => 'X-Forwarded-For',
            'forwarded_host' => 'X-Forwarded-Host',
            'forwarded_proto' => 'X-Forwarded-Proto',
            'forwarded_port' => 'X-Forwarded-Port',
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isTrustedProxy($request)) {
            return $handler->handle($request);
        }

        $request = $this->rewriteRequest($request);

        return $handler->handle($request);
    }

    /**
     * Check if the current request comes from a trusted proxy.
     */
    private function isTrustedProxy(ServerRequestInterface $request): bool
    {
        if (empty($this->trustedProxies)) {
            return false;
        }

        $remoteAddr = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if ($remoteAddr === null) {
            return false;
        }

        foreach ($this->trustedProxies as $proxy) {
            if ($proxy === '*') {
                return true;
            }

            if ($proxy === $remoteAddr) {
                return true;
            }

            // Basic CIDR matching
            if (str_contains($proxy, '/') && $this->ipInCidr($remoteAddr, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rewrite request with forwarded values.
     */
    private function rewriteRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        // Rewrite client IP
        $forwardedFor = $request->getHeaderLine($this->trustedHeaders['forwarded_for']);
        if ($forwardedFor !== '') {
            $ips = array_map('trim', explode(',', $forwardedFor));
            $clientIp = $ips[0]; // First IP = real client
            $request = $request->withAttribute('client_ip', $clientIp);
        }

        // Rewrite scheme (http/https)
        $forwardedProto = $request->getHeaderLine($this->trustedHeaders['forwarded_proto']);
        if ($forwardedProto !== '') {
            $scheme = strtolower(trim($forwardedProto));
            $uri = $request->getUri()->withScheme($scheme);
            $request = $request->withUri($uri, true);
        }

        // Rewrite host
        $forwardedHost = $request->getHeaderLine($this->trustedHeaders['forwarded_host']);
        if ($forwardedHost !== '') {
            $host = trim($forwardedHost);
            $uri = $request->getUri()->withHost($host);
            $request = $request->withUri($uri, true);
        }

        // Rewrite port
        $forwardedPort = $request->getHeaderLine($this->trustedHeaders['forwarded_port']);
        if ($forwardedPort !== '') {
            $port = (int) trim($forwardedPort);
            if ($port > 0 && $port <= 65535) {
                $uri = $request->getUri()->withPort($port);
                $request = $request->withUri($uri, true);
            }
        }

        return $request;
    }

    /**
     * Check if an IP address falls within a CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
