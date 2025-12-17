<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Exception;

trait Searchable
{
    /**
     * Search the model for a given term.
     *
     * @param string $term
     * @return \Plugs\Database\QueryBuilder
     */
    public function search(string $term)
    {
        $columns = $this->getSearchableColumns();

        if (empty($columns)) {
            throw new Exception("No searchable columns defined for model " . static::class);
        }

        $term = "%{$term}%";

        return $this->where(function ($query) use ($columns, $term) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $query->where($column, 'LIKE', $term);
                } else {
                    $query->orWhere($column, 'LIKE', $term);
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
        return property_exists($this, 'searchable') ? $this->searchable : [];
    }
}
