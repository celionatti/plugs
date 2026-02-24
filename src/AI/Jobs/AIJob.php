<?php

declare(strict_types=1);

namespace Plugs\AI\Jobs;

use Plugs\Queue\Contracts\ShouldQueue;

class AIJob implements ShouldQueue
{
    protected string $method;
    protected array $params;

    public function handle($data): void
    {
        $method = $data['method'] ?? '';
        $params = $data['params'] ?? [];

        if (empty($method)) {
            return;
        }

        ai()->{$method}(...$params);
    }

}
