<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Welcome Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;

class MakeWelcomeCommand extends Command
{
    protected string $description = 'Reset the welcome page to the default Laravel 12 design';

    public function handle(): int
    {
        $this->advancedHeader('Welcome Page', 'Generating default landing page');

        $stubPath = BASE_PATH . '/src/Console/Stubs/welcome.stub';
        $targetPath = BASE_PATH . '/resources/views/welcome.plug.php';

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return 1;
        }

        $content = file_get_contents($stubPath);
        
        if (file_put_contents($targetPath, $content) === false) {
            $this->error("Failed to write to: {$targetPath}");
            return 1;
        }

        $this->success("Welcome page successfully reset at: resources/views/welcome.plug.php");

        // Ensure assets directory exists
        $assetDir = BASE_PATH . '/public/assets/images';
        if (!is_dir($assetDir)) {
            mkdir($assetDir, 0755, true);
        }

        $this->info("Remember to ensure 'public/assets/images/plugs-6.png' is present for the full experience.");

        return 0;
    }
}
