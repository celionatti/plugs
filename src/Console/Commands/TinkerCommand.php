<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Tinker Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Throwable;

class TinkerCommand extends Command
{
    protected string $description = 'Interact with your application in a REPL';

    public function handle(): int
    {
        $this->advancedHeader('Plugs Tinker', 'Interactive REPL for ' . env('APP_NAME', 'Plugs Framework'));
        
        $this->info("Type 'exit' or 'quit' to leave tinker.");
        $this->newLine();

        // Ensure we have common namespaces ready
        $this->setupContext();

        while (true) {
            $input = $this->prompt();

            if (in_array(strtolower(trim($input)), ['exit', 'quit', 'die()', 'exit()'])) {
                break;
            }

            if (empty(trim($input))) {
                continue;
            }

            $this->execute($input);
        }

        $this->info("Goodbye!");

        return self::SUCCESS;
    }

    protected function setupContext(): void
    {
        // In a real REPL we might want to pre-load models, etc.
        // For this simple version, we'll try to set up the environment.
    }

    protected function prompt(): string
    {
        $cyan = "\e[36m";
        $reset = "\e[0m";

        if (function_exists('readline')) {
            // Echo the colored prompt separately to avoid readline ANSI issues
            echo $cyan . ">>> " . $reset;
            $input = readline("");
            
            if ($input !== false) {
                readline_add_history($input);
            }
            
            return (string) $input;
        }

        // Fallback for non-interactive shells
        echo "\n" . $cyan . ">>> " . $reset;
        return fgets(STDIN) ?: "";
    }

    protected function execute(string $code): void
    {
        // Append semicolon if missing for simple expressions
        $code = trim($code);
        if (!str_ends_with($code, ';') && !str_ends_with($code, '}')) {
            $code .= ';';
        }

        // Catch return value if it's a simple expression
        if (!str_starts_with($code, 'return ') && !preg_match('/^(echo|print|var_dump|exit|die|if|for|foreach|while|class|function|namespace|use)/', $code)) {
            $code = 'return ' . $code;
        }

        try {
            ob_start();
            $result = eval($code);
            $output = ob_get_clean();

            if ($output) {
                echo $output . "\n";
            }

            if ($result !== null) {
                $this->displayResult($result);
            }
        } catch (Throwable $e) {
            ob_end_clean();
            $this->error("Error: " . $e->getMessage());
            if ($this->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
        }
    }

    protected function displayResult(mixed $result): void
    {
        if (is_scalar($result)) {
            $this->line("=> " . var_export($result, true));
        } elseif (is_array($result)) {
            $this->line("=> Array(" . count($result) . ") " . json_encode($result, JSON_PRETTY_PRINT));
        } elseif (is_object($result)) {
            $this->line("=> " . get_class($result) . " " . json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $this->line("=> " . var_export($result, true));
        }
    }
}
