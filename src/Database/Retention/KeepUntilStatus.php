<?php

declare(strict_types=1);

namespace Plugs\Database\Retention;

use Plugs\Database\QueryBuilder;

class KeepUntilStatus implements RetentionRuleInterface
{
    protected string $status;
    protected int $days;
    protected string $statusColumn;
    protected string $dateColumn;

    public function __construct(string $status, int $days = 0, string $statusColumn = 'status', string $dateColumn = 'updated_at')
    {
        $this->status = $status;
        $this->days = $days;
        $this->statusColumn = $statusColumn;
        $this->dateColumn = $dateColumn;
    }

    /**
     * Apply the rule: Select records with specific status (and optionally older than X days).
     */
    public function apply(QueryBuilder $query): QueryBuilder
    {
        $query->where($this->statusColumn, '=', $this->status);

        if ($this->days > 0) {
            $date = date('Y-m-d H:i:s', strtotime("-{$this->days} days"));
            $query->where($this->dateColumn, '<', $date);
        }

        return $query;
    }
}
