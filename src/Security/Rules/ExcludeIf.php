<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

class ExcludeIf extends AbstractRule
{
    protected string $otherField;
    protected $value;

    public function __construct(string $otherField, $value)
    {
        $this->otherField = $otherField;
        $this->value = $value;
    }

    public function validate(string $attribute, $value, array $data): bool
    {
        return true;
    }

    public function shouldExclude(array $data): bool
    {
        return isset($data[$this->otherField]) && $data[$this->otherField] == $this->value;
    }
}
