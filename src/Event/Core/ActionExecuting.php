<?php

declare(strict_types=1);

namespace Plugs\Event\Core;

use Plugs\Event\Event;
use Plugs\Http\Message\ServerRequest as Request;

class ActionExecuting extends Event
{
    public function __construct(
        public Request $request,
        public $controller,
        public string $action,
        public array $params
    ) {
    }
}
