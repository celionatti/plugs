<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class Required extends AbstractRule
{
    protected string $message = 'The :attribute field is required.';

    public function validate(string $attribute, $value, array $data): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }
}
