<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\View\ViewEngineInterface;

class ViewClearCommand extends Command
{
    protected string $description = 'Clear all compiled view files';

    public function handle(): int
    {
        $this->title('View Cache Clear');

        $engine = $this->getViewEngine();

        if (!$engine) {
            $this->error('View engine not found. Make sure ViewModule is registered.');
            return 1;
        }

        $this->task('Clearing view cache', function () use ($engine) {
            $engine->clearCache();
            return true;
        });

        $this->box(
            "View cache cleared successfully!\n\n" .
            "The storage/views directory has been purged.",
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
