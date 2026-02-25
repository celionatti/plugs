<?php

declare(strict_types=1);

namespace Plugs\View;

/**
 * ErrorMessage - Manages validation errors for views
 *
 * Usage in controller:
 *
 * $errors = new ErrorMessage([
 *     'name' => ['Name is required', 'Name must be at least 3 characters'],
 *     'email' => ['Invalid email format']
 * ]);
 *
 * return $viewEngine->render('form', ['errors' => $errors]);
 */
class ErrorMessage implements \IteratorAggregate, \Countable, \JsonSerializable
{
    private array $errors = [];

    /**
     * Create a new ErrorMessage instance
     *
     * @param array $errors Associative array of field => error messages
     */
    public function __construct(array $errors = [])
    {
        foreach ($errors as $field => $messages) {
            $this->errors[$field] = is_array($messages) ? array_values($messages) : [$messages];
        }
    }

    /**
     * Check if a field has any errors
     *
     * @param string $field Field name
     * @return bool
     */
    public function has(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get the first error message for a field
     *
     * @param string $field Field name
     * @return string|null
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all error messages for a field
     *
     * @param string $field Field name
     * @return array
     */
    public function get(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get all errors
     *
     * @return array
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->errors as $messages) {
            $all = array_merge($all, $messages);
        }

        return $all;
    }

    /**
     * Check if there are any errors
     *
     * @return bool
     */
    public function any(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any errors (alias for any)
     *
     * @return bool
     */
    public function hasAny(): bool
    {
        return $this->any();
    }

    /**
     * Get count of fields with errors
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * Add an error message for a field
     *
     * @param string $field Field name
     * @param string $message Error message
     * @return self
     */
    public function add(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;

        return $this;
    }

    /**
     * Clear all errors or errors for a specific field
     *
     * @param string|null $field Field name (null to clear all)
     * @return self
     */
    public function clear(?string $field = null): self
    {
        if ($field === null) {
            $this->errors = [];
        } else {
            unset($this->errors[$field]);
        }

        return $this;
    }

    /**
     * Get all errors as raw array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->errors;
    }

    /**
     * Get all messages as a flat array
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->all();
    }

    /**
     * Get an iterator for the errors
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->errors);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->errors;
    }
}
