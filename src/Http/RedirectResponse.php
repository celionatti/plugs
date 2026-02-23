<?php

declare(strict_types=1);

namespace Plugs\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class RedirectResponse implements ResponseInterface
{
    protected ResponseInterface $response;
    protected string $url;
    protected int $status;
    protected array $flashData = [];
    protected array $flashErrors = [];
    protected array $flashInput = [];

    public function __construct(string $url, int $status = 302)
    {
        $this->url = $url;
        $this->status = $status;
        $this->response = ResponseFactory::createResponse($status)
            ->withHeader('Location', $url);
    }

    /**
     * Create a redirect response from current global context (referer)
     */
    public static function fromGlobal(string $fallback = '/', int $status = 302): self
    {
        $url = $_SERVER['HTTP_REFERER'] ?? $fallback;

        return new self($url, $status);
    }

    /**
     * Create a redirect to the previously intended URL.
     *
     * This is typically used after authentication to send the user
     * back to the page they originally tried to access.
     *
     * Usage: return RedirectResponse::intended('/dashboard');
     */
    public static function intended(string $default = '/', int $status = 302): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $url = $_SESSION['url.intended'] ?? $default;
        unset($_SESSION['url.intended']);

        return new self($url, $status);
    }

    /**
     * Add flash data to the session
     */
    public function with(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->with($k, $v);
            }
            return $this;
        }

        // Keep compatibility with standard flash types
        // If the user does ->with('success', 'Message'), we treat it as a flash message
        if (in_array($key, ['success', 'error', 'warning', 'info'], true) && is_string($value)) {
            return $this->withFlash($key, $value);
        }

        $this->flashData[$key] = $value;

        return $this;
    }

    /**
     * Flash input data
     */
    public function withInput(array $input = []): self
    {
        if (empty($input)) {
            // Get from current request
            $input = $_POST;
        }

        $this->flashInput = $input;

        return $this;
    }

    /**
     * Flash errors to the session
     */
    public function withErrors(mixed $errors, string $key = 'errors'): self
    {
        if ($errors instanceof \Plugs\View\ErrorMessage) {
            $this->flashErrors['_errors'] = $errors;
        } elseif (is_array($errors)) {
            $current = $this->flashErrors['_errors'] ?? new \Plugs\View\ErrorMessage();
            foreach ($errors as $field => $message) {
                $current->add(is_numeric($field) ? 'general' : (string) $field, $message);
            }
            $this->flashErrors['_errors'] = $current;
        } else {
            $current = $this->flashErrors['_errors'] ?? new \Plugs\View\ErrorMessage();
            $current->add('general', (string) $errors);
            $this->flashErrors['_errors'] = $current;
        }

        return $this;
    }

    /**
     * Flash a success message
     */
    public function withSuccess(string $message, ?string $title = null): self
    {
        return $this->withFlash('success', $message, $title);
    }

    /**
     * Flash an error message
     */
    public function withError(string $message, ?string $title = null): self
    {
        return $this->withFlash('error', $message, $title);
    }

    /**
     * Flash a warning message
     */
    public function withWarning(string $message, ?string $title = null): self
    {
        return $this->withFlash('warning', $message, $title);
    }

    /**
     * Flash an info message
     */
    public function withInfo(string $message, ?string $title = null): self
    {
        return $this->withFlash('info', $message, $title);
    }

    /**
     * Helper to flash to FlashMessage
     */
    protected function withFlash(string $type, string $message, ?string $title = null): self
    {
        $this->flashData[$type] = [
            'message' => $message,
            'title' => $title,
            'type' => $type,
            'timestamp' => time(),
        ];

        return $this;
    }

    /**
     * Execute the redirect and store flash data
     */
    public function send(): void
    {
        // Store flash data in session
        $this->storeFlashData();

        // Send headers and body from the underlying response
        if (method_exists($this->response, 'send')) {
            $this->response->send();
        } else {
            // Manual fallback if not framework response
            if (!headers_sent()) {
                header("HTTP/1.1 {$this->status}");
                foreach ($this->getHeaders() as $name => $values) {
                    foreach ($values as $value) {
                        header("{$name}: {$value}", false);
                    }
                }
            }
        }
        exit;
    }

    /**
     * Store flash data in session
     */
    protected function storeFlashData(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize flash nested array if not exists
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        foreach ($this->flashData as $key => $value) {
            $_SESSION['_flash'][$key] = $value;
        }

        if (!empty($this->flashErrors['_errors'])) {
            $_SESSION['_errors'] = $this->flashErrors['_errors'];
        }

        if (!empty($this->flashInput)) {
            $_SESSION['_old_input'] = $this->flashInput;
        }
    }

    /**
     * PSR-7 Implementation delegation
     */
    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion($version): ResponseInterface
    {
        $new = clone $this;
        $new->response = $this->response->withProtocolVersion($version);

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function hasHeader($name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader($name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine($name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        $new->response = $this->response->withHeader($name, $value);

        return $new;
    }

    public function withAddedHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        $new->response = $this->response->withAddedHeader($name, $value);

        return $new;
    }

    public function withoutHeader($name): ResponseInterface
    {
        $new = clone $this;
        $new->response = $this->response->withoutHeader($name);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        $new = clone $this;
        $new->response = $this->response->withBody($body);

        return $new;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $new = clone $this;
        $new->response = $this->response->withStatus($code, $reasonPhrase);

        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }

    public function __destruct()
    {
        // For CLI or when someone forgot to return the response
        if (PHP_SAPI !== 'cli' && !headers_sent() && $this->status >= 300 && $this->status < 400) {
            // $this->send(); // Optional: Automatic send can be dangerous if not careful
        }
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
