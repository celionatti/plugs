<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Support\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LocalizationMiddleware implements MiddlewareInterface
{
    /**
     * The translator instance.
     *
     * @var Translator
     */
    protected Translator $translator;

    /**
     * Create a new middleware instance.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Handle the incoming request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $this->detectLocale($request);

        $this->translator->setLocale($locale);

        $response = $handler->handle($request);

        return $response->withHeader('Content-Language', $locale);
    }

    /**
     * Detect the locale for the request.
     *
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function detectLocale(ServerRequestInterface $request): string
    {
        // 1. Check Query Parameter
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['lang'])) {
            return $queryParams['lang'];
        }

        // 2. Check Session (if available)
        $session = $request->getAttribute('session');
        if ($session && $session->has('locale')) {
            return $session->get('locale');
        }

        // 3. Check Cookie
        $cookies = $request->getCookieParams();
        if (!empty($cookies['locale'])) {
            return $cookies['locale'];
        }

        // 4. Check Accept-Language header
        $header = $request->getHeaderLine('Accept-Language');
        if (!empty($header)) {
            $parts = explode(',', $header);
            $primary = explode(';', $parts[0])[0];
            return trim($primary);
        }

        return $this->translator->getLocale();
    }
}
