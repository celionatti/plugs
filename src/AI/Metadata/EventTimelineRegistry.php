<?php

declare(strict_types=1);

namespace Plugs\AI\Metadata;

/**
 * EventTimelineRegistry records the sequence and timing of events.
 * It provides a historical context for AI to analyze the request flow.
 */
class EventTimelineRegistry
{
    private static array $timeline = [];
    private static float $startTime;

    public static function record(string $event, array $metadata = []): void
    {
        if (empty(self::$timeline)) {
            self::$startTime = microtime(true);
        }

        self::$timeline[] = [
            'event' => $event,
            'offset_ms' => round((microtime(true) - self::$startTime) * 1000, 2),
            'metadata' => $metadata,
        ];
    }

    public static function getTimeline(): array
    {
        return self::$timeline;
    }

    public static function clear(): void
    {
        self::$timeline = [];
    }
}
