<?php

declare(strict_types=1);

namespace Plugs\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/*
|--------------------------------------------------------------------------
| StackLogger
|--------------------------------------------------------------------------
|
| Writes log messages to multiple loggers simultaneously.
| Used by LogManager::stack() for multi-channel logging.
|
| Usage:
|   $stack = new StackLogger([$fileLogger, $stderrLogger]);
|   $stack->error('This goes to both channels');
*/

class StackLogger extends AbstractLogger
{
    /** @var LoggerInterface[] */
    private array $loggers;

    /**
     * @param LoggerInterface[] $loggers
     */
    public function __construct(array $loggers)
    {
        $this->loggers = $loggers;
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }
}
