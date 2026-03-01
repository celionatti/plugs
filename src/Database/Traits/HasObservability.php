<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\Observable;
use Plugs\Database\Observability\MetricsManager;
use ReflectionClass;

trait HasObservability
{
    public static ?Observable $observabilityConfig = null;

    public static function bootHasObservability(): void
    {
        $class = static::class;
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(Observable::class);

        if (empty($attributes)) {
            return;
        }

        static::$observabilityConfig = $attributes[0]->newInstance();

        if (static::$observabilityConfig->trackMetrics) {
            static::retrieved(function ($model) {
                MetricsManager::getInstance()->incrementModelMetric(static::class, 'loads');
            });

            static::saved(function ($model) {
                MetricsManager::getInstance()->incrementModelMetric(static::class, 'saves');
            });

            static::deleted(function ($model) {
                MetricsManager::getInstance()->incrementModelMetric(static::class, 'deletes');
            });
        }
    }

    /**
     * Get the observability metrics for this model class.
     */
    public static function getMetrics(): array
    {
        $report = MetricsManager::getInstance()->getReport();
        return $report['models'][static::class] ?? [];
    }
}
