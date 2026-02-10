<?php

declare(strict_types=1);

namespace Plugs\Database\Observability;

class MetricsManager
{
    private static ?self $instance = null;
    private array $metrics = [
        'queries' => [],
        'models' => [],
        'totals' => [
            'query_count' => 0,
            'total_time' => 0,
            'slow_queries' => 0,
        ],
    ];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function recordQuery(string $model, string $sql, float $time, bool $isSlow): void
    {
        $this->metrics['totals']['query_count']++;
        $this->metrics['totals']['total_time'] += $time;
        if ($isSlow) {
            $this->metrics['totals']['slow_queries']++;
        }

        if ($model) {
            if (!isset($this->metrics['models'][$model])) {
                $this->metrics['models'][$model] = [
                    'query_count' => 0,
                    'total_time' => 0,
                    'slow_queries' => 0,
                    'loads' => 0,
                    'saves' => 0,
                    'deletes' => 0,
                ];
            }
            $this->metrics['models'][$model]['query_count']++;
            $this->metrics['models'][$model]['total_time'] += $time;
            if ($isSlow) {
                $this->metrics['models'][$model]['slow_queries']++;
            }
        }
    }

    public function incrementModelMetric(string $model, string $metric): void
    {
        if (!isset($this->metrics['models'][$model])) {
            $this->metrics['models'][$model] = [
                'query_count' => 0,
                'total_time' => 0,
                'slow_queries' => 0,
                'loads' => 0,
                'saves' => 0,
                'deletes' => 0,
            ];
        }
        $this->metrics['models'][$model][$metric]++;
    }

    public function getReport(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        $this->metrics = [
            'queries' => [],
            'models' => [],
            'totals' => [
                'query_count' => 0,
                'total_time' => 0,
                'slow_queries' => 0,
            ],
        ];
    }
}
