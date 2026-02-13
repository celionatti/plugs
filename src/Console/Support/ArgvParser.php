<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| ArgvParser Class
|--------------------------------------------------------------------------
*/



class ArgvParser
{
    public function __construct(private array $argv)
    {
    }

    public function commandName(): ?string
    {
        $name = $this->argv[1] ?? null;

        if ($name && str_starts_with($name, '-')) {
            return null;
        }

        return $name;
    }

    public function input(): Input
    {
        $start = 2;
        if (isset($this->argv[1]) && str_starts_with($this->argv[1], '-')) {
            $start = 1;
        }

        $tokens = array_slice($this->argv, $start);
        $args = [];
        $opts = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $this->parseLongOption($token, $opts);
            } elseif (str_starts_with($token, '-')) {
                $this->parseShortOption($token, $opts);
            } else {
                $args[] = $token;
            }
        }

        $indexed = [];
        foreach ($args as $i => $value) {
            $indexed[(string) $i] = $value;
        }

        return new Input($indexed, $opts);
    }

    private function parseLongOption(string $token, array &$opts): void
    {
        $pair = substr($token, 2);
        if (str_contains($pair, '=')) {
            [$key, $value] = explode('=', $pair, 2);
            $opts[$key] = $this->castValue($value);
        } else {
            $opts[$pair] = true;
        }
    }

    private function parseShortOption(string $token, array &$opts): void
    {
        $flags = substr($token, 1);
        foreach (str_split($flags) as $flag) {
            $opts[$flag] = true;
        }
    }

    private function castValue(string $value): string|int|bool
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => is_numeric($value) ? (int) $value : $value,
        };
    }
}
