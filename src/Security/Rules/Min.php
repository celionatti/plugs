<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class Min extends AbstractRule
{
    protected int $min;
    protected string $message = 'The :attribute must be at least :min.';

    public function __construct(int $min)
    {
        $this->min = $min;
    }

    public function validate(string $attribute, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $size = $this->getSize($value);

        return $size >= $this->min;
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
        return str_replace(':min', (string) $this->min, parent::message());
    }
}
