<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Mass Assignment Exception
|--------------------------------------------------------------------------
|
| Thrown when an attribute is being assigned that is not in the model's
| fillable list. This protects against unintended mass assignment attacks.
*/

class MassAssignmentException extends PlugsException
{
    /**
     * The model class.
     *
     * @var string
     */
    protected string $model = '';

    /**
     * The rejected attribute key.
     *
     * @var string
     */
    protected string $attribute = '';

    /**
     * Create a new mass assignment exception.
     *
     * @param string $key
     * @param string $model
     */
    public function __construct(string $key = '', string $model = '')
    {
        $this->attribute = $key;
        $this->model = $model;

        $message = $key
            ? "Add [{$key}] to fillable to allow mass assignment on [{$model}]."
            : 'Mass assignment violation.';

        parent::__construct($message);
    }

    /**
     * Get the rejected attribute key.
     *
     * @return string
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * Get the model class.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
