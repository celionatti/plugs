<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

trait SoftDeletes
{
    protected $softDelete = false;
    protected $deletedAtColumn = 'deleted_at';

    public static function withoutTrashed()
    {
        return static::query();
    }

    public static function restoreAll(): bool
    {
        $instance = new static();
        if (!$instance->softDelete) {
            return false;
        }

        $sql = "UPDATE {$instance->table} SET {$instance->deletedAtColumn} = NULL
                WHERE {$instance->deletedAtColumn} IS NOT NULL";

        $instance->executeQuery($sql);
        return true;
    }

    public static function forceDeleteAll(): bool
    {
        $instance = new static();
        if (!$instance->softDelete) {
            return false;
        }

        $sql = "DELETE FROM {$instance->table} WHERE {$instance->deletedAtColumn} IS NOT NULL";
        $instance->executeQuery($sql);
        return true;
    }
}
