<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;
use Throwable;

class ExceptionThrown extends Event
{
    public function __construct(public Throwable $exception)
    {
    }
}
