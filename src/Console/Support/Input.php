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

    /**
     * Get the command name from arguments (if parsed from argv).
     */
    public function commandName(): ?string
    {
        return $this->arguments['_command'] ?? null;
    }
}
