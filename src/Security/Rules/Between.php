<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class Between extends AbstractRule
{
    protected int $min;
    protected int $max;
    protected string $message = 'The :attribute must be between :min and :max.';

    public function __construct($min, $max)
    {
        $this->min = (int) $min;
        $this->max = (int) $max;
    }

    public function validate(string $attribute, $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $size = $this->getSize($value);

        return $size >= $this->min && $size <= $this->max;
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
        return str_replace([':min', ':max'], [(string) $this->min, (string) $this->max], parent::message());
    }
}
