<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * PasswordField
 *
 * Represents a password model attribute.
 * Automatically hidden from serialization.
 */
class PasswordField extends Field
{
    protected ?int $minLength = null;
    protected bool $requireStrong = false;

    public function __construct()
    {
        // Passwords are hidden by default
        $this->isHidden = true;
    }

    /**
     * Set the minimum password length.
     */
    public function min(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    /**
     * Require a strong password (uppercase, lowercase, number, special char).
     */
    public function strong(): static
    {
        $this->requireStrong = true;

        return $this;
    }

    public function getCastType(): string
    {
        return 'string';
    }

    protected function getTypeRules(): array
    {
        $rules = ['string'];

        if ($this->minLength !== null) {
            $rules[] = "min:{$this->minLength}";
        }

        if ($this->requireStrong) {
            $rules[] = 'strong_password';
        }

        return $rules;
    }
}
