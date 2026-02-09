<?php

declare(strict_types=1);

namespace Plugs\Container;

class Inspector
{
    private array $trace = [];
    private array $graph = [];
    private bool $enabled = false;

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(string $abstract, array $parameters = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $parentId = end($this->trace) ?: null;
        $nodeId = $abstract;

        // Record dependency edge
        if ($parentId) {
            $this->graph[] = [
                'from' => $parentId,
                'to' => $nodeId,
                'type' => 'dependency',
            ];
        }

        $this->trace[] = $nodeId;
    }

    public function end(string $abstract, $instance = null): void
    {
        if (!$this->enabled) {
            return;
        }

        array_pop($this->trace);
    }

    public function getGraph(): array
    {
        return $this->graph;
    }

    public function generateMermaid(): string
    {
        $lines = ["graph TD"];
        foreach ($this->graph as $edge) {
            $from = $this->sanitizeId($edge['from']);
            $to = $this->sanitizeId($edge['to']);
            $lines[] = "    {$from} --> {$to}";
        }
        return implode("\n", array_unique($lines));
    }

    private function sanitizeId(string $id): string
    {
        return str_replace(['\\', ' '], ['_', '_'], $id);
    }
}
