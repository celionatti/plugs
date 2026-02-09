<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\QueryBuilder;

trait Prunable
{
    /**
     * Prune all prunable models from the database.
     *
     * @param  int  $chunkSize
     * @return int
     */
    public function pruneAll(int $chunkSize = 1000): int
    {
        $total = 0;

        $this->prunable()->chunkById($chunkSize, function ($models) use (&$total) {
            $models->each->prune();

            $total += $models->count();
        });

        return $total;
    }

    /**
     * Prune the model instance.
     *
     * @param  bool  $force
     * @return bool|null
     */
    public function prune(bool $force = false)
    {
        if ($this->hasLegalHold()) {
            return false;
        }

        if ($this->fireModelEvent('pruning', ['force' => $force]) === false) {
            return false;
        }

        $result = $force ? $this->forceDelete() : $this->delete();

        if ($result) {
            $this->fireModelEvent('pruned', ['force' => $force]);
        }

        return $result;
    }

    /**
     * Get the prunable model query.
     *
     * @return QueryBuilder
     *
     * @throws \LogicException
     */
    public function prunable(): QueryBuilder
    {
        $query = static::query();

        foreach ($this->retentionRules() as $rule) {
            $query = $rule->apply($query);
        }

        return $query;
    }

    /**
     * Define the retention rules for the model.
     *
     * @return array
     */
    public function retentionRules(): array
    {
        return [];
    }

    /**
     * Determine if the model has a legal hold.
     *
     * @return bool
     */
    public function hasLegalHold(): bool
    {
        return false;
    }

    /**
     * Fire the "pruning" event for the model.
     *
     * @deprecated Use fireModelEvent('pruning')
     * @return void
     */
    protected function firingPruningEvent(): void
    {
        // Handled via fireModelEvent in prune()
    }
}
