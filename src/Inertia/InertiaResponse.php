<?php

declare(strict_types=1);

namespace Plugs\Inertia;

/*
|--------------------------------------------------------------------------
| InertiaResponse Class
|--------------------------------------------------------------------------
|
| Handles both full-page HTML responses and XHR JSON responses for
| Inertia.js-style SPA navigation. Detects request type via X-Inertia
| header and responds appropriately.
*/

use Plugs\Http\ResponseFactory;
use Plugs\Http\Message\Stream;
use Plugs\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class InertiaResponse
{
    /**
     * Component name (e.g., 'Users/Index')
     */
    private string $component;

    /**
     * Props to pass to the component
     */
    private array $props;

    /**
     * Root view template name
     */
    private string $rootView;

    /**
     * Asset version for cache busting
     */
    private ?string $version;

    /**
     * Create a new Inertia response
     *
     * @param string $component Component name
     * @param array $props Component props
     * @param string $rootView Root view template
     * @param string|null $version Asset version
     */
    public function __construct(
        string $component,
        array $props = [],
        string $rootView = 'app',
        ?string $version = null
    ) {
        $this->component = $component;
        $this->props = $props;
        $this->rootView = $rootView;
        $this->version = $version;
    }

    /**
     * Get the page data array
     *
     * @param ServerRequestInterface|null $request Current request
     * @return array Page data structure
     */
    public function toArray(?ServerRequestInterface $request = null): array
    {
        $url = '/';

        if ($request !== null) {
            $uri = $request->getUri();
            $url = $uri->getPath();

            if ($query = $uri->getQuery()) {
                $url .= '?' . $query;
            }
        }

        return [
            'component' => $this->component,
            'props' => $this->resolveProps($this->props, $request),
            'url' => $url,
            'version' => $this->version ?? '',
        ];
    }

    /**
     * Convert to PSR-7 response
     *
     * @param ServerRequestInterface $request Current request
     * @return ResponseInterface PSR-7 response
     */
    public function toResponse(ServerRequestInterface $request): ResponseInterface
    {
        // Check if this is an Inertia XHR request
        if ($this->isInertiaRequest($request)) {
            return $this->toJsonResponse($request);
        }

        return $this->toFullPageResponse($request);
    }

    /**
     * Check if the request is an Inertia XHR request
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    private function isInertiaRequest(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('X-Inertia')
            && $request->getHeaderLine('X-Inertia') === 'true';
    }

    /**
     * Return JSON response for Inertia XHR requests
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function toJsonResponse(ServerRequestInterface $request): ResponseInterface
    {
        $pageData = $this->toArray($request);
        $json = json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $body = new Stream(fopen('php://temp', 'w+'));
        $body->write($json);
        $body->rewind();

        return new Response(200, $body, [
            'Content-Type' => 'application/json',
            'X-Inertia' => 'true',
            'Vary' => 'X-Inertia',
        ]);
    }

    /**
     * Return full HTML page response for initial page loads
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function toFullPageResponse(ServerRequestInterface $request): ResponseInterface
    {
        $pageData = $this->toArray($request);

        // Render the root view with page data
        $html = $this->renderRootView($pageData);

        return ResponseFactory::html($html, 200, [
            'Vary' => 'X-Inertia',
        ]);
    }

    /**
     * Render the root view template
     *
     * @param array $pageData Page data to embed
     * @return string Rendered HTML
     */
    private function renderRootView(array $pageData): string
    {
        // Try using the framework's view function if available
        if (function_exists('view')) {
            try {
                return view($this->rootView, [
                    'page' => $pageData,
                    'inertiaHead' => $this->generateInertiaHead($pageData),
                ]);
            } catch (\Throwable $e) {
                // Fall back to simple template if view fails
            }
        }

        // Fallback: generate basic HTML structure
        return $this->generateFallbackHtml($pageData);
    }

    /**
     * Generate Inertia head tags
     *
     * @param array $pageData
     * @return string
     */
    private function generateInertiaHead(array $pageData): string
    {
        $head = '';

        // Add version meta tag if available
        if (!empty($pageData['version'])) {
            $head .= sprintf(
                '<meta name="inertia-version" content="%s">',
                htmlspecialchars($pageData['version'], ENT_QUOTES, 'UTF-8')
            );
        }

        return $head;
    }

    /**
     * Generate fallback HTML when view engine is not available
     *
     * @param array $pageData
     * @return string
     */
    private function generateFallbackHtml(array $pageData): string
    {
        $encodedPage = htmlspecialchars(
            json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {$this->generateInertiaHead($pageData)}
    <title>App</title>
</head>
<body>
    <div id="app" data-page='{$encodedPage}'></div>
    <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
HTML;
    }

    /**
     * Resolve lazy props and merge shared props
     *
     * @param array $props
     * @param ServerRequestInterface|null $request
     * @return array
     */
    private function resolveProps(array $props, ?ServerRequestInterface $request = null): array
    {
        $resolved = [];
        $partialData = $this->getPartialData($request);
        $partialComponent = $this->getPartialComponent($request);

        foreach ($props as $key => $value) {
            // Skip lazy props unless specifically requested
            if ($value instanceof LazyProp) {
                // Only evaluate if partial request includes this key
                if ($partialComponent === $this->component && in_array($key, $partialData, true)) {
                    $resolved[$key] = $value();
                }
                continue;
            }

            // Handle callables (but not objects with __invoke unless LazyProp)
            if (is_callable($value) && !is_object($value)) {
                $resolved[$key] = $value();
                continue;
            }

            $resolved[$key] = $value;
        }

        // Merge with shared props from Inertia class
        return array_merge(Inertia::getSharedProps(), $resolved);
    }

    /**
     * Get partial data keys from request
     *
     * @param ServerRequestInterface|null $request
     * @return array
     */
    private function getPartialData(?ServerRequestInterface $request): array
    {
        if ($request === null) {
            return [];
        }

        $header = $request->getHeaderLine('X-Inertia-Partial-Data');

        if (empty($header)) {
            return [];
        }

        return array_filter(explode(',', $header));
    }

    /**
     * Get partial component from request
     *
     * @param ServerRequestInterface|null $request
     * @return string|null
     */
    private function getPartialComponent(?ServerRequestInterface $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $header = $request->getHeaderLine('X-Inertia-Partial-Component');

        return !empty($header) ? $header : null;
    }

    /**
     * Get the component name
     *
     * @return string
     */
    public function getComponent(): string
    {
        return $this->component;
    }

    /**
     * Get the props
     *
     * @return array
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * Add additional props
     *
     * @param array $props
     * @return self
     */
    public function with(array $props): self
    {
        $this->props = array_merge($this->props, $props);
        return $this;
    }

    /**
     * Magic method to allow string conversion for Router normalization
     *
     * @return string
     */
    public function __toString(): string
    {
        // This shouldn't normally be called, but provides fallback
        $request = function_exists('request') ? request() : null;

        if ($request !== null) {
            $response = $this->toResponse($request);
            return (string) $response->getBody();
        }

        return json_encode($this->toArray()) ?: '';
    }
}
