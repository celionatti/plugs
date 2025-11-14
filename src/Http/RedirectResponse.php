<?php

declare(strict_types=1);

namespace Plugs\Http;

use Psr\Http\Message\ResponseInterface;

class RedirectResponse
{
    protected string $url;
    protected int $status;
    protected array $headers = [];
    protected array $flashData = [];
    protected array $flashErrors = [];
    protected array $flashInput = [];

    public function __construct(string $url, int $status = 302)
    {
        $this->url = $url;
        $this->status = $status;
    }

    /**
     * Add flash data to the session
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->flashData = array_merge($this->flashData, $key);
        } else {
            $this->flashData[$key] = $value;
        }

        return $this;
    }

    /**
     * Flash input data
     */
    public function withInput(array $input = []): self
    {
        if (empty($input)) {
            // Get from current request
            $input = $_POST ?? [];
        }

        $this->flashInput = $input;

        return $this;
    }

    /**
     * Flash errors to the session
     */
    public function withErrors(array|string $errors, string $key = 'errors'): self
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }

        $this->flashErrors[$key] = $errors;

        return $this;
    }

    /**
     * Flash a success message
     */
    public function withSuccess(string $message): self
    {
        return $this->with('success', $message);
    }

    /**
     * Flash an error message
     */
    public function withError(string $message): self
    {
        return $this->with('error', $message);
    }

    /**
     * Flash a warning message
     */
    public function withWarning(string $message): self
    {
        return $this->with('warning', $message);
    }

    /**
     * Flash an info message
     */
    public function withInfo(string $message): self
    {
        return $this->with('info', $message);
    }

    /**
     * Add a header to the redirect
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Set a cookie with the redirect
     */
    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
        return $this;
    }

    /**
     * Add a fragment to the URL
     */
    public function withFragment(string $fragment): self
    {
        $this->url .= '#' . ltrim($fragment, '#');
        return $this;
    }

    /**
     * Add query parameters to the redirect URL
     */
    public function withQuery(array $params): self
    {
        $separator = str_contains($this->url, '?') ? '&' : '?';
        $this->url .= $separator . http_build_query($params);
        return $this;
    }

    /**
     * Execute the redirect and store flash data
     */
    public function send(): void
    {
        // Store flash data in session
        if (!empty($this->flashData) || !empty($this->flashErrors) || !empty($this->flashInput)) {
            $this->storeFlashData();
        }

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Send redirect header
        header("Location: {$this->url}", true, $this->status);
        exit;
    }

    /**
     * Store flash data in session
     */
    protected function storeFlashData(): void
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Store flash data
        if (!empty($this->flashData)) {
            foreach ($this->flashData as $key => $value) {
                $_SESSION['_flash'][$key] = $value;
            }
        }

        // Store flash errors
        if (!empty($this->flashErrors)) {
            foreach ($this->flashErrors as $key => $errors) {
                $_SESSION['_flash'][$key] = $errors;
            }
        }

        // Store flash input
        if (!empty($this->flashInput)) {
            $_SESSION['_flash']['_old_input'] = $this->flashInput;
        }

        // Mark flash data for deletion on next request
        $_SESSION['_flash']['_delete_next'] = true;
    }

    /**
     * Convert to PSR-7 Response (if using PSR-7 responses)
     */
    public function toResponse(): ResponseInterface
    {
        // Create empty response with redirect status
        $response = ResponseFactory::createResponse($this->status);
        $response = $response->withHeader('Location', $this->url);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        // Store flash data
        if (!empty($this->flashData) || !empty($this->flashErrors) || !empty($this->flashInput)) {
            $this->storeFlashData();
        }

        return $response;
    }

    /**
     * Get the redirect URL
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the status code
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Auto-send when object is destroyed (if not already sent)
     */
    public function __destruct()
    {
        // Only auto-send if headers haven't been sent yet
        if (!headers_sent()) {
            $this->send();
        }
    }

    /**
     * Allow using the object as a string
     */
    public function __toString(): string
    {
        return $this->url;
    }
}