<?php

declare(strict_types=1);

namespace Plugs\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use InvalidArgumentException;

class Logger extends AbstractLogger
{
    private string $logPath;

    public function __construct(string $logPath = null)
    {
        if ($logPath === null) {
            $logPath = defined('STORAGE_PATH')
                ? STORAGE_PATH . 'logs/plugs.log'
                : __DIR__ . '/../../storage/logs/plugs.log';
        }

        $this->logPath = $logPath;
        $this->ensureDirectoryExists();
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = []): void
    {
        $this->validateLevel($level);

        $message = $this->interpolate((string) $message, $context);
        $formatted = $this->format($level, $message, $context);

        file_put_contents($this->logPath, $formatted, FILE_APPEND | LOCK_EX);
    }

    private function validateLevel($level): void
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        if (!in_array($level, $levels)) {
            throw new InvalidArgumentException("Invalid log level: " . $level);
        }
    }

    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    private function format($level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';

        return sprintf("[%s] %s: %s%s%s", $timestamp, strtoupper($level), $message, $contextStr, PHP_EOL);
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
