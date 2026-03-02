<?php

declare(strict_types=1);

namespace Plugs\Payment\Exceptions;

use Exception;

class GatewayException extends Exception
{
    protected array $responseData;

    public function __construct(string $message, int $code = 0, array $responseData = [], Exception $previous = null)
    {
        $this->responseData = $responseData;
        parent::__construct($message, $code, $previous);
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
