<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

use Plugs\Security\Rules\RuleInterface;

abstract class AbstractRule implements RuleInterface
{
    /**
     * The validation error message.
     */
    protected string $message = 'The :attribute is invalid.';

    /**
     * The custom error message.
     */
    protected ?string $customMessage = null;

    /**
     * Run the validation rule.
     */
    abstract public function validate(string $attribute, $value, array $data): bool;

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return $this->customMessage ?: $this->message;
    }

    /**
     * Set a custom validation error message.
     */
    public function setMessage(string $message): self
    {
        $this->customMessage = $message;
        return $this;
    }

    /**
     * Convert the rule to a string.
     */
    public function __toString(): string
    {
        $class = explode('\\', static::class);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', end($class)));
    }
}
