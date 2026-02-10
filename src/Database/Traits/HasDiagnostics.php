<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\Diagnostic;
use ReflectionClass;

trait HasDiagnostics
{
    /**
     * Perform a health check on the model.
     *
     * @return array List of health issues found.
     */
    public function checkHealth(): array
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(Diagnostic::class);

        if (empty($attributes)) {
            return [];
        }

        $config = $attributes[0]->newInstance();
        $issues = [];

        foreach ($config->checks as $check) {
            $method = "diagnose" . ucfirst($check);
            if (method_exists($this, $method)) {
                $issues = array_merge($issues, $this->$method());
            }
        }

        return $issues;
    }

    /**
     * Detect orphaned child records (for BelongsTo relations).
     */
    protected function diagnoseOrphans(): array
    {
        $issues = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && (str_ends_with($returnType->getName(), 'BelongsToProxy') || $returnType->getName() === \Plugs\Database\Relations\BelongsToProxy::class)) {
                $relation = $method->invoke($this);
                $foreignKey = $relation->getForeignKey();
                $ownerKey = $relation->getOwnerKey();
                $relatedClass = $relation->getRelated();
                $relatedInstance = new $relatedClass();
                $relatedTable = $relatedInstance->getTable();

                $val = $this->getAttribute($foreignKey);
                if ($val !== null) {
                    try {
                        $exists = $relatedClass::query()->where($ownerKey, '=', $val)->exists();
                        if (!$exists) {
                            $issues[] = "Orphaned relation: '{$method->getName()}' references non-existent {$relatedTable}.{$ownerKey} ({$val})";
                        }
                    } catch (\PDOException $e) {
                        $issues[] = "Diagnostic warning: Could not verify orphans for '{$method->getName()}'. Table '{$relatedTable}' may be missing.";
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Warn about missing indexes for common discovery columns.
     */
    protected function diagnoseIndexes(): array
    {
        $issues = [];
        $connection = \Plugs\Database\Connection::getInstance();
        $table = $this->getTable();

        if (!method_exists($connection, 'getTableIndexes')) {
            return ["Diagnostic skipped: Connection does not support getTableIndexes()"];
        }

        $columnsWithIndexes = [];
        try {
            $indexes = $connection->getTableIndexes($table);

            foreach ($indexes as $index) {
                foreach ($index['columns'] as $col) {
                    $columnsWithIndexes[] = $col;
                }
            }
        } catch (\PDOException $e) {
            $issues[] = "Diagnostic error: Could not inspect indexes for '{$table}'. Database error: " . $e->getMessage();
        }

        $reflection = new ReflectionClass($this);
        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && (str_contains($returnType->getName(), 'BelongsToProxy') || str_contains($returnType->getName(), 'HasManyProxy'))) {
                $relation = $method->invoke($this);
                $fk = $relation->getForeignKey();
                if (!in_array($fk, $columnsWithIndexes)) {
                    $issues[] = "Missing index: Foreign key '{$fk}' on table '{$table}' (requested by {$method->getName()}) is not indexed.";
                }
            }
        }

        return $issues;
    }

    /**
     * Run custom integrity checks.
     */
    protected function diagnoseIntegrity(): array
    {
        if (method_exists($this, 'validateHealth')) {
            return $this->validateHealth();
        }
        return [];
    }
}
