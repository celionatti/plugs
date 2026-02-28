<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;
use Psr\Http\Message\ResponseInterface as Response;

class ResponseSent extends Event
{
    public function __construct(public Response $response)
    {
    }
}
