<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;
use Plugs\Plugs;

class ApplicationBootstrapped extends Event
{
    public function __construct(public Plugs $app)
    {
    }
}
