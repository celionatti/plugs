<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

/**
 * @phpstan-consistent-constructor
 * @phpstan-ignore trait.unused
 */
trait SoftDeletes
{
    protected $softDelete = false;
    protected $deletedAtColumn = 'deleted_at';

    public function restore(): bool
    {
        if (!$this->softDelete || !$this->exists()) {
            return false;
        }

        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $result = static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->update([$this->deletedAtColumn => null]);

        if ($result) {
            $this->attributes[$this->deletedAtColumn] = null;
            $this->original[$this->deletedAtColumn] = null;
            $this->fireModelEvent('restored');
        }

        return $result;
    }

    /**
     * Perform the actual delete query.
     */
    protected function performDelete(): bool
    {
        if ($this->softDelete) {
            $this->attributes[$this->deletedAtColumn] = date('Y-m-d H:i:s');

            return static::query()
                ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
                ->update([$this->deletedAtColumn => $this->attributes[$this->deletedAtColumn]]);
        }

        return static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete();
    }

    public static function withoutTrashed()
    {
        return static::query();
    }

    public static function restoreAll(): bool
    {
        /** @phpstan-ignore new.static */
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
        /** @phpstan-ignore new.static */
        $instance = new static();
        if (!$instance->softDelete) {
            return false;
        }

        return static::query()
            ->whereNotNull($instance->deletedAtColumn)
            ->delete();
    }
}
