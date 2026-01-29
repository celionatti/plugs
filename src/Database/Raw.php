<?php

declare(strict_types=1);

namespace Plugs\Database;

class Raw
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
