<?php

declare(strict_types=1);

namespace Plugs\Database\Observability;

use Plugs\Base\Model\PlugModel;
use ReflectionClass;

class DiagnosticsManager
{
    private static ?self $instance = null;

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

    /**
     * Run diagnostics on a specific model class or instance.
     */
    public function check(string|PlugModel $model): array
    {
        $instance = is_string($model) ? new $model() : $model;

        if (method_exists($instance, 'checkHealth')) {
            return $instance->checkHealth();
        }

        return [];
    }

    /**
     * Run diagnostics on multiple models.
     */
    public function checkMultiple(array $models): array
    {
        $report = [];
        foreach ($models as $model) {
            $class = is_string($model) ? $model : get_class($model);
            $report[$class] = $this->check($model);
        }
        return $report;
    }

    /**
     * Get a summary of all health issues found.
     */
    public function getSummary(array $report): array
    {
        $summary = [
            'total_models' => count($report),
            'total_issues' => 0,
            'models_with_issues' => 0,
            'issues' => [],
        ];

        foreach ($report as $model => $modelIssues) {
            if (!empty($modelIssues)) {
                $summary['total_issues'] += count($modelIssues);
                $summary['models_with_issues']++;
                $summary['issues'][$model] = $modelIssues;
            }
        }

        return $summary;
    }
}
