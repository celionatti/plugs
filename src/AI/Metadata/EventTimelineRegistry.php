<?php

declare(strict_types=1);

namespace Plugs\AI\Metadata;

/**
 * EventTimelineRegistry records the sequence and timing of events.
 * It provides a historical context for AI to analyze the request flow.
 */
class EventTimelineRegistry
{
    private array $timeline = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function record(string $event, array $metadata = []): void
    {
        $this->timeline[] = [
            'event' => $event,
            'offset_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            'metadata' => $metadata,
        ];
    }

    public function getTimeline(): array
    {
        return $this->timeline;
    }

    public function clear(): void
    {
        $this->timeline = [];
        $this->startTime = microtime(true);
    }
}
