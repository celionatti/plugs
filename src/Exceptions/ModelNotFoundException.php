<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Model Not Found Exception
|--------------------------------------------------------------------------
|
| Thrown when a model is not found in the database.
*/

class ModelNotFoundException extends PlugsException
{
    /**
     * HTTP status code for not found errors.
     *
     * @var int
     */
    protected int $statusCode = 404;

    /**
     * The affected model class.
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
     * Create a new model not found exception.
     *
     * @param string $model
     * @param array|int|string $ids
     */
    public function __construct(string $model = '', array|int|string $ids = [])
    {
        $this->model = $model;
        $this->ids = (array) $ids;

        $message = $this->formatMessage();
        parent::__construct($message);
    }

    /**
     * Set the affected model and IDs.
     *
     * @param string $model
     * @param array|int|string $ids
     * @return static
     */
    public function setModel(string $model, array|int|string $ids = []): static
    {
        $this->model = $model;
        $this->ids = (array) $ids;
        $this->message = $this->formatMessage();

        return $this;
    }

    /**
     * Get the affected model class.
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

    /**
     * Format the exception message.
     *
     * @return string
     */
    protected function formatMessage(): string
    {
        if (empty($this->model)) {
            return 'No query results for model.';
        }

        $class = class_basename($this->model);

        if (empty($this->ids)) {
            return "No query results for model [{$class}].";
        }

        $ids = implode(', ', $this->ids);

        return "No query results for model [{$class}] with ID(s) [{$ids}].";
    }
}

/**
 * Get the class "basename" of the given object / class.
 *
 * @param string|object $class
 * @return string
 */
function class_basename(string|object $class): string
{
    $class = is_object($class) ? get_class($class) : $class;

    return basename(str_replace('\\', '/', $class));
}
