<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| ArgvParser Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Support\Input;


class ArgvParser
{
    public function __construct(private array $argv) {}

    public function commandName(): ?string
    {
        // php bin/framework <name> [args]
        return $this->argv[1] ?? null;
    }

    public function input(): Input
    {
        // Shift script + command
        $tokens = array_slice($this->argv, 2);
        $args   = [];
        $opts   = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $pair = substr($token, 2);
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', $pair, 2);
                    $opts[$k] = $this->cast($v);
                } else {
                    $opts[$pair] = true; // boolean flag
                }
            } elseif (str_starts_with($token, '-')) {
                $flags = substr($token, 1);
                foreach (str_split($flags) as $flag) {
                    $opts[$flag] = true;
                }
            } else {
                $args[] = $token;
            }
        }

        // Map numeric args to placeholders {0},{1} and also allow named passthrough later
        $assoc = [];
        foreach ($args as $i => $value) {
            $assoc[(string)$i] = $value;
        }

        return new Input($assoc, $opts);
    }

    private function cast(string $v): string|int|bool
    {
        if ($v === 'true') return true;
        if ($v === 'false') return false;
        if (is_numeric($v)) return (int)$v;
        return $v;
    }
}
