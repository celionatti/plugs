<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

interface RuleInterface
{
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $data
     * @return bool|string
     */
    public function validate(string $attribute, $value, array $data);

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string;
}
