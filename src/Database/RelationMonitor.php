<?php

declare(strict_types=1);

namespace Plugs\Database;

use Plugs\Base\Model\PlugModel;

class RelationMonitor
{
    private static ?self $instance = null;
    private array $lazyLoads = [];
    private array $queries = [];
    private array $queryStack = [];
    private bool $enabled = true;

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

    public function enable(bool $enable = true): void
    {
        $this->enabled = $enable;
    }

    public function trackLazyLoad(PlugModel $model, string $relation): void
    {
        if (!$this->enabled)
            return;

        $modelClass = get_class($model);
        $collectionId = $model->getCollectionId() ?? 'standalone';

        $this->lazyLoads[$collectionId][$modelClass][$relation][] = [
            'model_id' => $model->getAttribute($model->getPrimaryKeyName()),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            'time' => microtime(true)
        ];

        // Potential N+1 detection: 3+ identical lazy loads in the same collection
        $count = count($this->lazyLoads[$collectionId][$modelClass][$relation]);
        if ($count >= 3 && $collectionId !== 'standalone') {
            $this->handleN1Detected($model, $relation);
        }
    }

    private function handleN1Detected(PlugModel $model, string $relation): void
    {
        $collection = $model->getCollection();
        if ($collection && method_exists($collection, 'predictiveLoad')) {
            $collection->predictiveLoad($relation);
        }
    }

    public function pushQueryContext(string $id): void
    {
        $this->queryStack[] = $id;
    }

    public function popQueryContext(): void
    {
        array_pop($this->queryStack);
    }

    public function trackQuery(string $sql, array $params, float $time): string
    {
        if (!$this->enabled)
            return '';

        $id = bin2hex(random_bytes(4));
        $parentId = end($this->queryStack) ?: null;

        $this->queries[] = [
            'id' => $id,
            'parent_id' => $parentId,
            'sql' => $sql,
            'params' => $params,
            'time' => $time,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        return $id;
    }

    public function getLazyLoads(): array
    {
        return $this->lazyLoads;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function reset(): void
    {
        $this->lazyLoads = [];
        $this->queries = [];
    }
}
