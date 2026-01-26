<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class MakePaginationTemplateCommand extends Command
{
    protected string $description = 'Generate a professional pagination template for your project';

    private array $templates = [
        'bootstrap' => 'Classic Bootstrap-styled pagination',
        'tailwind' => 'Modern Tailwind CSS pagination with results info',
        'simple' => 'Minimalist Previous/Next pagination',
    ];

    public function handle(): int
    {
        $this->info("Available Pagination Templates:");
        foreach ($this->templates as $key => $desc) {
            $this->line(" - <success>{$key}</success>: {$desc}");
        }

        $type = $this->argument('0') ?? $this->ask('Which template would you like to generate?', 'tailwind');
        $type = strtolower($type);

        if (!isset($this->templates[$type])) {
            $this->error("Template [{$type}] not found.");
            return 1;
        }

        $targetDir = resource_path('views/pagination');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $path = $targetDir . "/{$type}.plug.php";

        if (file_exists($path)) {
            if (!$this->confirm("Template [{$type}] already exists. Overwrite?")) {
                $this->warning("Skipped.");
                return 0;
            }
        }

        $content = $this->getTemplateContent($type);

        if ($content === null) {
            $this->error("Could not load template source for [{$type}].");
            return 1;
        }

        file_put_contents($path, $content);

        $this->success("Pagination Template [{$type}] generated successfully!");
        $this->info("Location: resources/views/pagination/{$type}.plug.php");
        $this->line("Usage: <info>\$paginator->links('pagination.{$type}')</info>");

        return 0;
    }

    private function getTemplateContent(string $type): ?string
    {
        $stubPath = __DIR__ . "/../../Paginator/Templates/{$type}.plug.php";

        if (file_exists($stubPath)) {
            return file_get_contents($stubPath);
        }

        return null;
    }
}
