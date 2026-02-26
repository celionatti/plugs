<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

use Plugs\Security\Rules\RuleInterface;

class Password implements RuleInterface
{
    protected int $min = 8;
    protected bool $letters = false;
    protected bool $mixedCase = false;
    protected bool $numbers = false;
    protected bool $symbols = false;
    protected static ?Password $defaultInstance = null;

    /**
     * Create a new password rule instance.
     */
    public function __construct(int $min = 8)
    {
        $this->min = $min;
    }

    /**
     * Set the minimum length of the password.
     */
    public function min(int $min): self
    {
        $this->min = $min;
        return $this;
    }

    /**
     * Require at least one letter.
     */
    public function letters(): self
    {
        $this->letters = true;
        return $this;
    }

    /**
     * Require at least one uppercase and one lowercase letter.
     */
    public function mixedCase(): self
    {
        $this->mixedCase = true;
        return $this;
    }

    /**
     * Require at least one number.
     */
    public function numbers(): self
    {
        $this->numbers = true;
        return $this;
    }

    /**
     * Require at least one symbol.
     */
    public function symbols(): self
    {
        $this->symbols = true;
        return $this;
    }

    /**
     * Get the default password rule configuration.
     */
    public static function defaults(): self
    {
        if (static::$defaultInstance === null) {
            static::$defaultInstance = (new static(8))
                ->letters()
                ->numbers()
                ->symbols();
        }

        return clone static::$defaultInstance;
    }

    /**
     * Set the default password rule configuration.
     */
    public static function setDefaults(Password $rule): void
    {
        static::$defaultInstance = $rule;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, $value, array $data): bool
    {
        if (empty($value)) {
            return true;
        }

        if (strlen((string) $value) < $this->min) {
            return false;
        }

        if ($this->letters && !preg_match('/[a-zA-Z]/', (string) $value)) {
            return false;
        }

        if ($this->mixedCase && (!preg_match('/[a-z]/', (string) $value) || !preg_match('/[A-Z]/', (string) $value))) {
            return false;
        }

        if ($this->numbers && !preg_match('/[0-9]/', (string) $value)) {
            return false;
        }

        if ($this->symbols && !preg_match('/[^a-zA-Z0-9]/', (string) $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $requirements = ["at least {$this->min} characters"];

        if ($this->letters)
            $requirements[] = 'at least one letter';
        if ($this->mixedCase)
            $requirements[] = 'both uppercase and lowercase letters';
        if ($this->numbers)
            $requirements[] = 'at least one number';
        if ($this->symbols)
            $requirements[] = 'at least one special character';

        return 'The :attribute must meet the following requirements: ' . implode(', ', $requirements) . '.';
    }

    /**
     * Convert the rule to a string.
     */
    public function __toString(): string
    {
        return 'password_rule_obj';
    }
}
