<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Facades\AI;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class AIIndexDocsCommand extends Command
{
    protected string $signature = 'ai:index-docs';
    protected string $description = 'Index the framework documentation for AI-aware chat (RAG)';

    public function handle(): int
    {
        $this->title('AI Documentation Indexer');

        $docsPath = base_path('docs');
        if (!is_dir($docsPath)) {
            $this->error("Documentation path not found: {$docsPath}");
            return 1;
        }

        $this->info("Scanning documentation in {$docsPath}...");

        $files = $this->getMarkdownFiles($docsPath);
        $totalFiles = count($files);

        if ($totalFiles === 0) {
            $this->warning("No markdown files found to index.");
            return 0;
        }

        $this->info("Found {$totalFiles} files. Starting indexing...");

        $this->withProgressBar($totalFiles, function ($file) {
            $this->indexFile($file);
        });

        $this->newLine();
        $this->success("Documentation indexing completed successfully!");

        return 0;
    }

    protected function getMarkdownFiles(string $path): array
    {
        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/^.+\.md$/i', RegexIterator::GET_MATCH);

        $files = [];
        foreach ($regex as $match) {
            $files[] = $match[0];
        }

        return $files;
    }

    protected function indexFile(string $path): void
    {
        $content = file_get_contents($path);
        $relativePath = str_replace(base_path(), '', $path);

        // Chunk content by headings (simple strategy)
        $chunks = preg_split('/(?=^#)/m', $content, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (empty($chunk))
                continue;

            $metadata = [
                'source' => $relativePath,
                'path' => $path,
                'type' => 'documentation'
            ];

            // Embed and store
            try {
                $vector = AI::embed($chunk);
                AI::vector()->add(uniqid('doc_'), $vector, $metadata);
            } catch (\Throwable $e) {
                // Silently skip if one chunk fails, or log in verbose mode
                if ($this->isVerbose()) {
                    $this->error("Failed to index chunk from {$relativePath}: " . $e->getMessage());
                }
            }
        }
    }
}
