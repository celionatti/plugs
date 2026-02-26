<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class Max extends AbstractRule
{
    protected int $max;
    protected string $message = 'The :attribute must not exceed :max.';

    public function __construct($max)
    {
        $this->max = (int) $max;
    }

    public function validate(string $attribute, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $size = $this->getSize($value);

        return $size <= $this->max;
    }

    protected function getSize($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        } elseif (is_array($value)) {
            return count($value);
        }

        return mb_strlen((string) $value);
    }

    public function message(): string
    {
        return str_replace(':max', (string) $this->max, parent::message());
    }
}
