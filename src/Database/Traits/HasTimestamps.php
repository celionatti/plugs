<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

trait HasTimestamps
{
    public $timestamps = true;

    public function touch(): bool
    {
        if (!$this->timestamps) {
            return false;
        }

        $this->setAttribute('updated_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    public static function touchAll(array $ids): bool
    {
        $instance = new static();

        if (!$instance->timestamps) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$instance->table} SET updated_at = ?
                WHERE {$instance->primaryKey} IN ({$placeholders})";

        $bindings = array_merge([date('Y-m-d H:i:s')], $ids);
        $instance->executeQuery($sql, $bindings);

        return true;
    }
}
