<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Database Exception
|--------------------------------------------------------------------------
|
| Thrown when a database operation fails.
*/

class DatabaseException extends PlugsException
{
    /**
     * The SQL query that caused the exception.
     *
     * @var string|null
     */
    protected ?string $sql = null;

    /**
     * The bindings for the query.
     *
     * @var array
     */
    protected array $bindings = [];

    /**
     * Create a new database exception.
     *
     * @param string $message
     * @param string|null $sql
     * @param array $bindings
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'A database error occurred.',
        ?string $sql = null,
        array $bindings = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    /**
     * Create a new database exception from a PDOException.
     *
     * @param \PDOException $e
     * @param string|null $sql
     * @param array $bindings
     * @return static
     */
    public static function fromPDOException(
        \PDOException $e,
        ?string $sql = null,
        array $bindings = []
    ): static {
        return new static($e->getMessage(), $sql, $bindings, $e);
    }

    /**
     * Get the SQL query.
     *
     * @return string|null
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }

    /**
     * Get the bindings for the query.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Convert the exception to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        // Only include SQL in non-production environments
        if (!$this->isProduction()) {
            $data['sql'] = $this->sql;
            $data['bindings'] = $this->bindings;
        }

        return $data;
    }

    /**
     * Check if in production mode.
     *
     * @return bool
     */
    protected function isProduction(): bool
    {
        return strtolower(getenv('APP_ENV') ?: 'production') === 'production';
    }
}
