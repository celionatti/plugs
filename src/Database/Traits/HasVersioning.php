<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\Versioned;
use Plugs\Database\Exceptions\ConcurrencyException;
use ReflectionClass;

trait HasVersioning
{
    public $original_version = null;

    protected static ?string $versionColumn = null;

    public static function bootHasVersioning(): void
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(Versioned::class);

        if (empty($attributes)) {
            return;
        }

        $versioned = $attributes[0]->newInstance();
        static::$versionColumn = $versioned->column;

        static::updating(function ($model) {
            $column = static::$versionColumn;
            if (isset($model->attributes[$column])) {
                $model->original_version = $model->attributes[$column];
                $model->setAttribute($column, ((int) $model->attributes[$column]) + 1);
            } else {
                // Initialize version if not set
                $model->setAttribute($column, 1);
                $model->original_version = null;
            }
        });
    }

    /**
     * Apply versioning constraints to the update query.
     */
    protected function applyVersioningConstraints($query)
    {
        if (static::$versionColumn && property_exists($this, 'original_version') && $this->original_version !== null) {
            $query->where(static::$versionColumn, '=', $this->original_version);
        }
    }
}
