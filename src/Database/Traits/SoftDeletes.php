<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

/**
 * @phpstan-ignore trait.unused
 */
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

        return static::query()
            ->whereNotNull($instance->deletedAtColumn)
            ->update([$instance->deletedAtColumn => null]);
    }

    public static function forceDeleteAll(): bool
    {
        $instance = new static();
        if (!$instance->softDelete) {
            return false;
        }

        return static::query()
            ->whereNotNull($instance->deletedAtColumn)
            ->delete();
    }
}
