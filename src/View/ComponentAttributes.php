<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| ComponentAttributes Class
|--------------------------------------------------------------------------
|
| @package Plugs\View
*/


class ComponentAttributes
{
    private array $attributes;
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }
    public function merge(array $attributes): self
    {
        return new self(array_merge($this->attributes, $attributes));
    }
    public function __toString(): string
    {
        $parts = [];
        foreach ($this->attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value)
                    $parts[] = $key;
            } else {
                $parts[] = sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            }
        }
        return implode(' ', $parts);
    }
}