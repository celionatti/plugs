<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;

class QueryExecuted extends Event
{
    public function __construct(
        public string $sql,
        public array $bindings,
        public float $time,
        public string $connectionName
    ) {
    }
}
