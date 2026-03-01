<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\View\ViewEngineInterface;

class ViewCacheCommand extends Command
{
    protected string $description = 'Pre-compile all application views for faster rendering';

    public function handle(): int
    {
        $this->title('View Cache Generator');

        // Resolve View Engine
        $engine = $this->getViewEngine();

        if (!$engine) {
            $this->error('View engine not found. Make sure ViewModule is registered.');
            return 1;
        }

        $this->task('Compiling all views', function () use ($engine) {
            $result = $engine->compileAll();

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->error("Failed to compile: $error");
                }
            }

            $this->info("Compiled {$result['compiled']} views.");
            return true;
        });

        $this->box(
            "View compilation completed successfully!\n\n" .
            "Your application's views are now pre-compiled for production speed.",
            "✅ Success",
            "success"
        );

        return 0;
    }

    private function getViewEngine(): ?ViewEngineInterface
    {
        try {
            return app(ViewEngineInterface::class);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
