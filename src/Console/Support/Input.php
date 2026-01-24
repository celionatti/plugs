<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| Input Class
|--------------------------------------------------------------------------
*/

class Input
{
    /** @param array<string,string> $arguments @param array<string,string|int|bool> $options */
    public function __construct(
        public array $arguments,
        public array $options
    ) {
    }
}
