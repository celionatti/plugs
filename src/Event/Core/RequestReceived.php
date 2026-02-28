<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;
use Plugs\Http\Message\ServerRequest as Request;

class RequestReceived extends Event
{
    public function __construct(public Request $request)
    {
    }
}
