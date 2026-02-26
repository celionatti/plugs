<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class Exclude extends AbstractRule
{
    public function validate(string $attribute, $value, array $data): bool
    {
        return true; // Exclude itself doesn't fail validation
    }
}
