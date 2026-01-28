<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class DownCommand extends Command
{
    protected string $signature = 'down {--message= : The message for the maintenance mode} {--retry=60 : The number of seconds after which the request may be retried} {--secret= : The secret phrase that may be used to bypass maintenance mode}';
    protected string $description = 'Put the application into maintenance / demo mode';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Maintenance Mode');

        $message = $this->option('message');
        $retry = $this->option('retry');
        $secret = $this->option('secret');

        $this->section('Configuration');
        $this->keyValue('Status', 'Down (Maintenance)');
        if ($message)
            $this->keyValue('Message', (string) $message);
        $this->keyValue('Retry After', (string) $retry . ' seconds');
        if ($secret)
            $this->keyValue('Bypass Secret', (string) $secret);
        $this->newLine();

        if ($this->isProduction() && !$this->isForce()) {
            if (!$this->confirm('Application is in PRODUCTION. Are you sure you want to take it down?', false)) {
                $this->warning('Maintenance mode cancelled.');
                return 0;
            }
        }

        $this->checkpoint('executing');
        $this->task('Configuring maintenance mode', function () use ($message, $retry, $secret) {
            $payload = [
                'time' => time(),
                'message' => $message,
                'retry' => $retry,
                'secret' => $secret,
            ];

            $directory = storage_path('framework');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents(
                $directory . '/maintenance.json',
                json_encode($payload, JSON_PRETTY_PRINT)
            );
            usleep(200000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Application is now in maintenance mode!\n\n" .
            ($secret ? "Bypass Secret: {$secret}\n" : "") .
            "Time: {$this->formatTime($this->elapsed())}",
            "❌ System Down",
            "warning"
        );

        if ($secret) {
            $this->section('Bypass Information');
            $this->info("To bypass maintenance mode, visit: " . url("/" . $secret));
            $this->newLine();
        }

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
