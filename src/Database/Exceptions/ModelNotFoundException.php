<?php

declare(strict_types=1);

namespace Plugs\Database\Exceptions;

use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    /**
     * The name of the affected model.
     *
     * @var string
     */
    protected string $model = '';

    /**
     * The affected model IDs.
     *
     * @var array
     */
    protected array $ids = [];

    /**
     * Set the affected model and identifiers.
     *
     * @param  string  $model
     * @param  array|int|string  $ids
     * @return $this
     */
    public function setModel(string $model, $ids = []): self
    {
        $this->model = $model;
        $this->ids = (array) $ids;

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' ' . implode(', ', $this->ids);
        }

        return $this;
    }

    /**
     * Get the affected model.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the affected model IDs.
     *
     * @return array
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
