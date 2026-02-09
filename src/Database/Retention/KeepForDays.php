<?php

declare(strict_types=1);

namespace Plugs\Database\Retention;

use Plugs\Database\QueryBuilder;

class KeepForDays implements RetentionRuleInterface
{
    protected int $days;
    protected string $column;

    public function __construct(int $days, string $column = 'created_at')
    {
        $this->days = $days;
        $this->column = $column;
    }

    /**
     * Apply the rule: Select records older than X days.
     */
    public function apply(QueryBuilder $query): QueryBuilder
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$this->days} days"));

        return $query->where($this->column, '<', $date);
    }
}
