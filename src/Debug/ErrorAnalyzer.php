<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Throwable;
use ReflectionClass;

class ErrorAnalyzer
{
    /**
     * Analyze an exception and return a list of actionable suggestions.
     *
     * @param Throwable $e
     * @return array<string>
     */
    public function analyze(Throwable $e): array
    {
        $suggestions = [];
        $message = $e->getMessage();

        // 1. Undefined Variable
        if (preg_match('/Undefined variable \$?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $message, $matches)) {
            $variable = $matches[1];
            $suggestions[] = "Ensure that \$$variable is defined and initialized before this line.";

            $similar = $this->findSimilarVariableInFile($variable, $e->getFile());
            if ($similar) {
                $suggestions[] = "Did you mean **\$$similar**?";
            }
        }

        // 2. Class Not Found
        if (preg_match('/Class [\'"]?(.+?)[\'"]? not found/i', $message, $matches)) {
            $class = $matches[1];
            $suggestions[] = "Are you missing a `use` statement for `$class`?";
            $suggestions[] = "Did you run `composer dump-autoload` recently?";

            $similar = $this->findSimilarClass($class);
            if ($similar) {
                $suggestions[] = "Did you mean **$similar**?";
            }
        }

        // 3. Call to undefined function
        if (preg_match('/Call to undefined function ([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\(\)/', $message, $matches)) {
            $function = $matches[1];
            $similar = $this->findSimilarFunction($function);
            if ($similar) {
                $suggestions[] = "Did you mean **$similar()**?";
            }
            $suggestions[] = "If this is a custom or vendor function, ensure the file is loaded.";
        }

        // 4. Call to undefined method
        if (preg_match('/Call to undefined method (.+?)::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\(\)/', $message, $matches)) {
            $class = $matches[1];
            $method = $matches[2];

            $similar = $this->findSimilarMethod($class, $method);
            if ($similar) {
                $suggestions[] = "Did you mean **$similar()** on class `$class`?";
            }
        }

        // 5. Database common errors (PDO)
        if ($e instanceof \PDOException || str_contains(get_class($e), 'Database')) {
            if (str_contains($message, 'Connection refused')) {
                $suggestions[] = "Check if your database server is running.";
                $suggestions[] = "Verify DB_HOST and DB_PORT in your `.env` file.";
            } elseif (str_contains($message, 'Base table or view not found')) {
                $suggestions[] = "Did you forget to run your migrations? Try `php plugs migrate`.";
            } elseif (str_contains($message, 'Access denied for user')) {
                $suggestions[] = "Verify DB_USERNAME and DB_PASSWORD in your `.env` file.";
            }
        }

        // 6. Plugs Specific
        if (str_contains(get_class($e), 'RouteNotFoundException')) {
            $suggestions[] = "Check your `routes/` directory if the route is defined.";
            $suggestions[] = "Did you forget to add the correct HTTP method (e.g., GET, POST)?";
        }

        return $suggestions;
    }

    protected function findSimilarVariableInFile(string $target, string $file): ?string
    {
        if (!file_exists($file))
            return null;

        $content = file_get_contents($file);
        $tokens = token_get_all($content);
        $variables = [];

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $varName = ltrim($token[1], '$');
                if ($varName !== 'this' && $varName !== $target) {
                    $variables[] = $varName;
                }
            }
        }

        $variables = array_unique($variables);
        return $this->getClosestMatch($target, $variables);
    }

    protected function findSimilarClass(string $target): ?string
    {
        $classes = get_declared_classes();
        $baseName = basename(str_replace('\\', '/', $target));

        $closest = null;
        $shortest = -1;

        foreach ($classes as $class) {
            $classBaseName = basename(str_replace('\\', '/', $class));
            $lev = levenshtein($baseName, $classBaseName);
            if ($lev <= 3) {
                if ($lev === 0) {
                    $closest = $class;
                    $shortest = 0;
                    break;
                }
                if ($lev < $shortest || $shortest < 0) {
                    $closest = $class;
                    $shortest = $lev;
                }
            }
        }

        return $closest;
    }

    protected function findSimilarFunction(string $target): ?string
    {
        $functions = get_defined_functions()['internal'] ?? [];
        $functions = array_merge($functions, get_defined_functions()['user'] ?? []);
        return $this->getClosestMatch($target, $functions);
    }

    protected function findSimilarMethod(string $class, string $method): ?string
    {
        if (!class_exists($class))
            return null;

        try {
            $reflection = new ReflectionClass($class);
            $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());
            return $this->getClosestMatch($method, $methods);
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    protected function getClosestMatch(string $target, array $candidates, int $threshold = 3): ?string
    {
        $shortest = -1;
        $closest = null;

        foreach ($candidates as $candidate) {
            $lev = levenshtein($target, $candidate);

            if ($lev <= $threshold) {
                if ($lev === 0) {
                    $closest = $candidate;
                    $shortest = 0;
                    break;
                }
                if ($lev < $shortest || $shortest < 0) {
                    $closest = $candidate;
                    $shortest = $lev;
                }
            }
        }

        return $closest;
    }
}
