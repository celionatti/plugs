<?php

declare(strict_types=1);

namespace Plugs\Database\Optimization;

use Plugs\Database\Connection;

class OptimizationManager
{
    protected Connection $connection;
    protected QueryAnalyzer $analyzer;
    protected IndexSuggester $suggester;
    protected SlowQueryLogger $logger;
    protected array $config;

    public function __construct(Connection $connection, array $config = [])
    {
        $this->connection = $connection;
        $this->config = array_merge([
            'enabled' => true,
            'slow_threshold' => 1.0,
            'log_path' => 'storage/logs/slow_queries.json',
            'analyze_on_runtime' => false, // Set to true to run EXPLAIN on every query (high overhead!)
        ], $config);

        $this->analyzer = new QueryAnalyzer($connection);
        $this->suggester = new IndexSuggester();
        $this->logger = new SlowQueryLogger($this->config['log_path']);
    }

    /**
     * Process a query after execution.
     */
    public function process(string $sql, array $params, float $time, ?array $backtrace = null): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $isSlow = $time >= $this->config['slow_threshold'];

        if ($isSlow) {
            $analysis = $this->analyzer->analyze($sql, $params);
            $suggestions = $this->suggester->suggest($sql);

            $this->logger->log([
                'sql' => $sql,
                'params' => $params,
                'time' => $time,
                'backtrace' => $backtrace,
                'analysis' => $analysis,
                'suggestions' => $suggestions
            ]);
        }
    }

    public function getAnalyzer(): QueryAnalyzer
    {
        return $this->analyzer;
    }

    public function getSuggester(): IndexSuggester
    {
        return $this->suggester;
    }

    public function getLogger(): SlowQueryLogger
    {
        return $this->logger;
    }
}
