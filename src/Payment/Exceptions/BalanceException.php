<?php

declare(strict_types=1);

namespace Plugs\Payment\Exceptions;

class BalanceException extends GatewayException
{
    // Specialized for insufficient funds or balance errors
}
