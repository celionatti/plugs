<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;
use Plugs\Http\Message\ServerRequest as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ActionExecuted extends Event
{
    public function __construct(
        public Request $request,
        public Response $response
    ) {
    }
}
