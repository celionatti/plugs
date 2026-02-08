<?php

declare(strict_types=1);

namespace Plugs\Database;

use Exception;
use PDO;
use Plugs\Base\Model\PlugModel;

/**
 * BelongsToMany Relationship Proxy
 * Provides attach, sync, detach methods for many-to-many relationships
 */
class BelongsToManyProxy
{
    protected PlugModel $parent;
    protected string $relationName;
    /** @var array{pivotTable: string, foreignPivotKey: string, relatedPivotKey: string, parentKey: string} */
    protected array $config;

    /**
     * @param array{pivotTable: string, foreignPivotKey: string, relatedPivotKey: string, parentKey: string} $config
     */
    public function __construct(PlugModel $parent, string $relationName, array $config)
    {
        $this->parent = $parent;
        $this->relationName = $relationName;
        $this->config = $config;
    }

    /**
     * Get the related models (for compatibility with Collection)
     */
    public function get(): Collection
    {
        return $this->parent->{$this->relationName};
    }

    /**
     * Sync pivot table relationships
     * Replaces all existing relationships with the provided IDs
     *
     * @param array<int>|int $ids Array of IDs or single ID to sync
     * @param bool $detaching Whether to detach records not in the list
     * @return array{attached: array<int>, detached: array<int>, updated: array<int>}
     */
    public function sync($ids, bool $detaching = true): array
    {
        // Normalize IDs to array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $pivotTable = $this->config['pivotTable'];
        $foreignPivotKey = $this->config['foreignPivotKey'];
        $relatedPivotKey = $this->config['relatedPivotKey'];
        $parentKey = $this->config['parentKey'];

        $parentId = $this->parent->getAttribute($parentKey);

        if (!$parentId) {
            throw new Exception("Parent model must exist before syncing relationships");
        }

        // Get current relationship IDs
        $currentIds = $this->getCurrentPivotIds();

        // Determine what to attach and detach
        $idsToAttach = array_diff($ids, $currentIds);
        $idsToDetach = $detaching ? array_diff($currentIds, $ids) : [];
        $idsToUpdate = array_intersect($ids, $currentIds);

        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        // Begin transaction
        $this->parent::beginTransaction();

        try {
            // Detach removed relationships
            if (!empty($idsToDetach)) {
                $this->detachPivot($idsToDetach);
                $changes['detached'] = $idsToDetach;
            }

            // Attach new relationships
            if (!empty($idsToAttach)) {
                $this->attachPivot($idsToAttach);
                $changes['attached'] = $idsToAttach;
            }

            // Record updated (existing) relationships
            $changes['updated'] = $idsToUpdate;

            $this->parent::commit();

            // Clear cache if enabled
            $this->clearCache();

            return $changes;
        } catch (Exception $e) {
            $this->parent::rollBack();

            throw $e;
        }
    }

    /**
     * Attach relationships to pivot table
     * Adds new relationships without removing existing ones
     *
     * @param array<int>|int $ids Array of IDs or single ID to attach
     * @param array<string, mixed> $attributes Additional pivot attributes
     * @param bool $touch Whether to update timestamps
     * @return void
     */
    public function attach($ids, array $attributes = [], bool $touch = true): void
    {
        // Normalize IDs to array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $parentKey = $this->config['parentKey'];
        $parentId = $this->parent->getAttribute($parentKey);

        if (!$parentId) {
            throw new Exception("Parent model must exist before attaching relationships");
        }

        // Get existing relationship IDs to avoid duplicates
        $existingIds = $this->getCurrentPivotIds();

        // Only attach IDs that don't already exist
        $idsToAttach = array_diff($ids, $existingIds);

        if (empty($idsToAttach)) {
            return; // Nothing to attach
        }

        $this->parent::beginTransaction();

        try {
            $this->attachPivot($idsToAttach, $attributes, $touch);

            $this->parent::commit();

            // Clear cache if enabled
            $this->clearCache();
        } catch (Exception $e) {
            $this->parent::rollBack();

            throw $e;
        }
    }

    /**
     * Detach relationships from pivot table
     * Removes relationships without affecting others
     *
     * @param array<int>|int|null $ids Array of IDs, single ID, or null to detach all
     * @return int Number of relationships detached
     */
    public function detach($ids = null): int
    {
        $parentKey = $this->config['parentKey'];
        $parentId = $this->parent->getAttribute($parentKey);

        if (!$parentId) {
            return 0;
        }

        // Normalize IDs to array
        if ($ids !== null && !is_array($ids)) {
            $ids = [$ids];
        }

        $this->parent::beginTransaction();

        try {
            $count = $this->detachPivot($ids);

            $this->parent::commit();

            // Clear cache if enabled
            $this->clearCache();

            return $count;
        } catch (Exception $e) {
            $this->parent::rollBack();

            throw $e;
        }
    }

    /**
     * Toggle relationships in pivot table
     * Attaches if not present, detaches if present
     *
     * @param array<int>|int $ids Array of IDs or single ID to toggle
     * @return array{attached: array<int>, detached: array<int>}
     */
    public function toggle($ids): array
    {
        // Normalize IDs to array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $parentKey = $this->config['parentKey'];
        $parentId = $this->parent->getAttribute($parentKey);

        if (!$parentId) {
            throw new Exception("Parent model must exist before toggling relationships");
        }

        // Get current relationship IDs
        $currentIds = $this->getCurrentPivotIds();

        // Determine what to attach and detach
        $idsToAttach = array_diff($ids, $currentIds);
        $idsToDetach = array_intersect($ids, $currentIds);

        $changes = [
            'attached' => [],
            'detached' => [],
        ];

        $this->parent::beginTransaction();

        try {
            // Detach existing
            if (!empty($idsToDetach)) {
                $this->detachPivot($idsToDetach);
                $changes['detached'] = $idsToDetach;
            }

            // Attach new
            if (!empty($idsToAttach)) {
                $this->attachPivot($idsToAttach);
                $changes['attached'] = $idsToAttach;
            }

            $this->parent::commit();

            // Clear cache if enabled
            $this->clearCache();

            return $changes;
        } catch (Exception $e) {
            $this->parent::rollBack();

            throw $e;
        }
    }

    /**
     * Update existing pivot record attributes
     *
     * @param int $id The related model ID
     * @param array<string, mixed> $attributes Attributes to update
     * @return bool
     */
    public function updatePivot(int $id, array $attributes): bool
    {
        $pivotTable = $this->config['pivotTable'];
        $foreignPivotKey = $this->config['foreignPivotKey'];
        $relatedPivotKey = $this->config['relatedPivotKey'];
        $parentKey = $this->config['parentKey'];

        $parentId = $this->parent->getAttribute($parentKey);

        if (!$parentId || empty($attributes)) {
            return false;
        }

        $setClauses = [];
        $bindings = [];

        foreach ($attributes as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $bindings[] = $value;
        }

        // Add updated_at if not present
        if (!array_key_exists('updated_at', $attributes)) {
            $setClauses[] = "updated_at = ?";
            $bindings[] = date('Y-m-d H:i:s');
        }

        $bindings[] = $parentId;
        $bindings[] = $id;

        $sql = "UPDATE {$pivotTable} SET " . implode(', ', $setClauses) .
            " WHERE {$foreignPivotKey} = ? AND {$relatedPivotKey} = ?";

        try {
            $this->executeQuery($sql, $bindings);
            $this->clearCache();

            return true;
        } catch (Exception $e) {
            error_log("Failed to update pivot: " . $e->getMessage());

            return false;
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Get current pivot IDs
     * @return array<int>
     */
    private function getCurrentPivotIds(): array
    {
        $pivotTable = $this->config['pivotTable'];
        $foreignPivotKey = $this->config['foreignPivotKey'];
        $relatedPivotKey = $this->config['relatedPivotKey'];
        $parentKey = $this->config['parentKey'];

        $parentId = $this->parent->getAttribute($parentKey);

        $sql = "SELECT {$relatedPivotKey} FROM {$pivotTable} WHERE {$foreignPivotKey} = ?";
        $stmt = $this->executeQuery($sql, [$parentId]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $results);
    }

    /**
     * Attach pivot records
     * @param array<int> $ids
     * @param array<string, mixed> $attributes
     */
    private function attachPivot(array $ids, array $attributes = [], bool $touch = true): void
    {
        if (empty($ids)) {
            return;
        }

        $pivotTable = $this->config['pivotTable'];
        $foreignPivotKey = $this->config['foreignPivotKey'];
        $relatedPivotKey = $this->config['relatedPivotKey'];
        $parentKey = $this->config['parentKey'];

        $parentId = $this->parent->getAttribute($parentKey);
        $records = [];
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $id) {
            $record = array_merge($attributes, [
                $foreignPivotKey => $parentId,
                $relatedPivotKey => $id,
            ]);

            // Add timestamps if touching
            if ($touch) {
                $record['created_at'] = $now;
                $record['updated_at'] = $now;
            }

            $records[] = $record;
        }

        // Batch insert
        $columns = array_keys($records[0]);
        $columnList = implode(', ', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $values = implode(', ', array_fill(0, count($records), $placeholders));

        $sql = "INSERT INTO {$pivotTable} ({$columnList}) VALUES {$values}";

        $bindings = [];
        foreach ($records as $record) {
            foreach ($columns as $column) {
                $bindings[] = $record[$column] ?? null;
            }
        }

        $this->executeQuery($sql, $bindings);
    }

    /**
     * Detach pivot records
     * @param array<int>|null $ids
     */
    private function detachPivot(?array $ids = null): int
    {
        $pivotTable = $this->config['pivotTable'];
        $foreignPivotKey = $this->config['foreignPivotKey'];
        $relatedPivotKey = $this->config['relatedPivotKey'];
        $parentKey = $this->config['parentKey'];

        $parentId = $this->parent->getAttribute($parentKey);

        if ($ids === null) {
            // Detach all
            $sql = "DELETE FROM {$pivotTable} WHERE {$foreignPivotKey} = ?";
            $bindings = [$parentId];
        } else {
            // Detach specific IDs
            if (empty($ids)) {
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM {$pivotTable} WHERE {$foreignPivotKey} = ? AND {$relatedPivotKey} IN ({$placeholders})";
            $bindings = array_merge([$parentId], $ids);
        }

        $stmt = $this->executeQuery($sql, $bindings);

        return $stmt->rowCount();
    }

    /**
     * Execute query using parent model's connection
     * @param array<mixed> $bindings
     * @return \PDOStatement
     */
    private function executeQuery(string $sql, array $bindings = []): \PDOStatement
    {
        // Use reflection to call protected method
        $reflection = new \ReflectionMethod($this->parent, 'executeQuery');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->parent, $sql, $bindings);
    }

    /**
     * Clear cache if enabled
     */
    private function clearCache(): void
    {
        $reflection = new \ReflectionProperty(get_class($this->parent), 'cacheEnabled');
        $reflection->setAccessible(true);

        if ($reflection->getValue()) {
            $this->parent::flushCache();
        }
    }
}
