<?php

declare(strict_types=1);

namespace Plugs\Database\Contracts;

/**
 * CastsAttributes
 * 
 * Interface for custom model cast classes.
 */
interface CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Plugs\Base\Model\PlugModel  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes);

    /**
     * Prepare the given value for storage.
     *
     * @param  \Plugs\Base\Model\PlugModel  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes);
}
