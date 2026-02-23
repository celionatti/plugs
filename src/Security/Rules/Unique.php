<?php

declare(strict_types=1);

namespace Plugs\Security\Rules;

use Plugs\Security\Rules\RuleInterface;

class Unique implements RuleInterface
{
    protected string $table;
    protected string $column;
    protected $ignoreId = null;
    protected string $idColumn = 'id';
    protected array $wheres = [];
    protected string $message = 'The :attribute has already been taken.';

    public function __construct(string $table, ?string $column = null)
    {
        $this->table = $table;
        $this->column = $column ?? '';
    }

    /**
     * Ignore the given ID during validation.
     *
     * @param mixed $id
     * @param string|null $idColumn
     * @return $this
     */
    public function ignore($id, ?string $idColumn = null): self
    {
        $this->ignoreId = $id;
        if ($idColumn) {
            $this->idColumn = $idColumn;
        }

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function where(string $column, $value): self
    {
        $this->wheres[] = [$column, $value];

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $data
     * @return bool
     */
    public function validate(string $attribute, $value, array $data): bool
    {
        if (empty($value)) {
            return true;
        }

        $column = $this->column ?: $attribute;

        $query = db($this->table)->where($column, '=', $value);

        if ($this->ignoreId !== null) {
            $query->where($this->idColumn, '!=', $this->ignoreId);
        }

        foreach ($this->wheres as $where) {
            $query->where($where[0], '=', $where[1]);
        }

        return !$query->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Set the validation error message.
     *
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Convert the rule to a string representation for the Validator.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'unique_rule_obj';
    }
}
