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
     * @return bool|null
     */
    public function prune()
    {
        $this->firingPruningEvent();

        return $this->delete();
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
        throw new \LogicException('Please implement the prunable method on your model.');
    }

    /**
     * Fire the "pruning" event for the model.
     *
     * @return void
     */
    protected function firingPruningEvent(): void
    {
        if (method_exists($this, 'pruning')) {
            $this->pruning();
        }
    }
}
