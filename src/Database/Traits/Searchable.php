<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Exception;

/**
 * @phpstan-ignore trait.unused
 */
trait Searchable
{
    /**
     * Search the model for a given term.
     *
     * @param \Plugs\Database\QueryBuilder $query
     * @param string $term
     * @return \Plugs\Database\QueryBuilder
     */
    public function scopeSearch($query, string $term)
    {
        $columns = $this->getSearchableColumns();

        if (empty($columns)) {
            throw new Exception("No searchable columns defined for model " . static::class);
        }

        $term = "%{$term}%";

        return $query->where(function ($q) use ($columns, $term) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'LIKE', $term);
                } else {
                    $q->orWhere($column, 'LIKE', $term);
                }
            }
        });
    }

    /**
     * Get the searchable columns for the model.
     *
     * @return array
     */
    public function getSearchableColumns(): array
    {
        return property_exists($this, 'searchableColumns') ? $this->searchableColumns :
            (property_exists($this, 'searchable') ? $this->searchable : []);
    }
}
