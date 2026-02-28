<?php

declare(strict_types=1);

namespace Plugs\Kernel;

use Plugs\Console\ConsoleKernel as ConsoleCommandKernel;
use Plugs\Console\ConsolePlugs;

/**
 * CLI Kernel — console command pipeline.
 *
 * No HTTP middleware, no session, no CSRF, no routing.
 * Only boots database and console services needed for commands.
 */
class CliKernel extends AbstractKernel
{
    /**
     * CLI has no HTTP middleware layers.
     */
    protected array $middlewareLayers = [
        'security' => [],
        'performance' => [],
        'business' => [],
    ];

    private ?ConsoleCommandKernel $consoleKernel = null;
    private ?ConsolePlugs $consolePlug = null;

    protected function bootServices(): void
    {
        // Initialize the console command system
        $this->consoleKernel = new ConsoleCommandKernel();
        $this->consolePlug = new ConsolePlugs($this->consoleKernel);

        // Register in container for access
        $this->container->singleton(ConsoleCommandKernel::class, fn() => $this->consoleKernel);
        $this->container->singleton(ConsolePlugs::class, fn() => $this->consolePlug);
    }

    /**
     * Run the console application.
     *
     * @param array $argv Command-line arguments
     * @return int Exit code
     */
    public function handle(array $argv): int
    {
        if (!$this->isBooted()) {
            $this->boot();
        }

        return $this->consolePlug->run($argv);
    }

    /**
     * Get the underlying console kernel for command registration.
     */
    public function getConsoleKernel(): ConsoleCommandKernel
    {
        return $this->consoleKernel;
    }
}
