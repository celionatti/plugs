<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class Email extends AbstractRule
{
    protected string $message = 'The :attribute must be a valid email address.';

    public function validate(string $attribute, $value, array $data): bool
    {
        if (empty($value)) {
            return true;
        }

        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
